<?php

namespace App\Services;

use App\Events\AlertaCreada;
use App\Events\PasoRegistrado;
use App\Models\Alerta;
use App\Models\Caseta;
use App\Models\Configuracion;
use App\Models\EventoHardware;
use App\Models\Paso;
use App\Models\Usuario;
use App\Models\Vehiculo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Flujo completo de un frame de la caseta:
 *  ESP32 "inferencia" → INFERENCE_URL/inferir → normaliza placa → vehículo + tarifa → descuenta saldo
 *  → guarda evidencia anotada → paso + alerta → tiempo real (Pusher/Reverb) → ESP32 "ok" | "alerta".
 */
class PasoService
{
    public function __construct(private InferenciaClient $inferencia)
    {
    }

    /**
     * @param  string  $modo  'auto' = flujo de video (2–5 fps): no persiste frames sin placa legible;
     *                        'manual' = captura del operador: siempre registra (incluida placa ilegible).
     */
    public function procesarFrame(string $bytes, Caseta $caseta, ?Usuario $usuario, string $origen = 'api', string $modo = 'manual'): array
    {
        EventoHardware::emitir('inferencia', ['caseta_id' => $caseta->id, 'mensaje' => 'Inferencia en curso']);

        try {
            $ia = $this->inferencia->inferir($bytes);
        } catch (Throwable $e) {
            Log::warning('Inferencia fallida: '.$e->getMessage());
            if ($modo === 'auto') {
                EventoHardware::emitir('idle', ['caseta_id' => $caseta->id, 'mensaje' => 'Error de inferencia']);

                return ['registrado' => false, 'motivo' => 'error_inferencia', 'mensaje' => $e->getMessage()];
            }
            $paso = $this->persistir($caseta, $usuario, $origen, [], null, null, 'error_inferencia', 0, null, null, $e->getMessage());
            $this->crearAlerta($paso, 'error_inferencia', null, 'Servidor de inferencia no disponible: '.Str::limit($e->getMessage(), 150), 'media');
            $this->notificar($paso);

            return ['registrado' => true, 'paso' => $paso->resumen(), 'mensaje' => $e->getMessage()];
        }

        $placaIa = $ia['placa'] ?? [];
        $placa = PlacaService::corregirFormato($placaIa['texto'] ?? '');
        $legible = $placa !== '' && PlacaService::formatoValido($placa)
            && (float) ($placaIa['confianza'] ?? 0) >= (float) config('peaje.umbral_placa', 0.35);
        $hayVehiculo = ! empty($ia['vehiculo']);

        // --- Flujo de video: se ignoran frames vacíos o con placa aún ilegible ---------------------
        if ($modo === 'auto' && ! $legible) {
            EventoHardware::emitir('idle', ['caseta_id' => $caseta->id]);

            return ['registrado' => false, 'motivo' => $hayVehiculo ? 'placa_no_legible_aun' : 'sin_vehiculo',
                'inferencia' => $this->sinImagen($ia)];
        }

        // --- Anti-duplicados: el mismo vehículo en frames consecutivos no se cobra dos veces ------
        if ($legible) {
            $previo = Paso::where('caseta_id', $caseta->id)->where('placa_detectada', $placa)
                ->where('created_at', '>=', now()->subSeconds((int) config('peaje.dedup_segundos', 60)))
                ->latest()->first();
            if ($previo) {
                EventoHardware::emitir($previo->estado === 'autorizado' ? 'ok' : 'alerta',
                    ['caseta_id' => $caseta->id, 'placa' => $placa, 'monto' => (float) $previo->monto, 'paso_id' => $previo->id]);

                return ['registrado' => false, 'duplicado' => true, 'motivo' => 'duplicado', 'paso' => $previo->resumen(),
                    'inferencia' => $this->sinImagen($ia)];
            }
        }

        $evidencia = $this->guardarEvidencia($ia['imagen_anotada_base64'] ?? null);

        if (! $legible) {
            $paso = $this->persistir($caseta, $usuario, $origen, $ia, null, null, 'placa_ilegible', 0, $evidencia, null,
                'No se pudo leer la placa');
            $this->crearAlerta($paso, 'placa_ilegible', null, 'Placa ilegible en '.$caseta->nombre.'. Verificar manualmente.', 'media');
            $this->notificar($paso);

            return ['registrado' => true, 'paso' => $paso->resumen(), 'inferencia' => $this->sinImagen($ia)];
        }

        [$vehiculo, $nota] = PlacaService::buscarVehiculo($placa);
        [$categoria, $ejes] = $this->categoriaYEjes($ia, $vehiculo);
        $tarifa = TarifaService::obtener($categoria, $ejes);
        $monto = $tarifa ? (float) $tarifa->monto : 0.0;

        $paso = DB::transaction(function () use ($vehiculo, $caseta, $usuario, $origen, $ia, $placa, $tarifa, $monto, $evidencia, $nota, $categoria, $ejes) {
            if (! $vehiculo) {
                return $this->persistir($caseta, $usuario, $origen, $ia, $placa, null, 'no_registrada', $monto, $evidencia, $tarifa, $nota,
                    $categoria, $ejes);
            }
            $v = Vehiculo::whereKey($vehiculo->id)->lockForUpdate()->first();
            $saldo = (float) $v->saldo;
            if ($v->activo && $saldo >= $monto) {
                $v->saldo = round($saldo - $monto, 2);
                $v->save();

                return $this->persistir($caseta, $usuario, $origen, $ia, $placa, $v, 'autorizado', $monto, $evidencia, $tarifa, $nota,
                    $categoria, $ejes, $saldo, (float) $v->saldo);
            }

            return $this->persistir($caseta, $usuario, $origen, $ia, $placa, $v, 'sin_saldo', $monto, $evidencia, $tarifa,
                $nota ?? ($v->activo ? null : 'Vehículo inactivo'), $categoria, $ejes, $saldo, $saldo);
        });

        if ($paso->estado === 'no_registrada') {
            $this->crearAlerta($paso, 'no_registrada', $placa,
                "Placa {$placa} identificada pero NO registrada en {$caseta->nombre}: posible evasión.", 'alta');
        } elseif ($paso->estado === 'sin_saldo') {
            $this->crearAlerta($paso, 'sin_saldo', $placa,
                "Placa {$placa} sin saldo suficiente (requiere Bs ".number_format($monto, 2).", saldo Bs ".number_format((float) $paso->saldo_anterior, 2).').', 'alta');
        }
        $this->notificar($paso);

        return ['registrado' => true, 'paso' => $paso->resumen(), 'inferencia' => $this->sinImagen($ia)];
    }

    /** Categoría y ejes a cobrar: IA (si confiable) o datos registrados del vehículo; mínimo 2 ejes. */
    private function categoriaYEjes(array $ia, ?Vehiculo $vehiculo): array
    {
        $catIa = $ia['vehiculo']['categoria'] ?? null;
        $confIa = (float) ($ia['vehiculo']['confianza'] ?? 0);
        $prioridad = Configuracion::obtener('PRIORIDAD_CATEGORIA', config('peaje.prioridad_categoria', 'ia'));

        if ($vehiculo && ($prioridad === 'registro' || ! $catIa || $confIa < 0.5)) {
            $categoria = $vehiculo->categoria;
        } else {
            $categoria = $catIa ?: ($vehiculo->categoria ?? 'auto');
        }
        // Ejes: M3 en vista lateral; en vista frontal/posterior (null) se usan los registrados o el mínimo 2
        $ejes = $ia['ejes'] ?? null;
        if ($ejes === null) {
            $ejes = $vehiculo->ejes ?? 2;
        }

        return [$categoria, max(2, (int) $ejes)];
    }

    private function persistir(Caseta $caseta, ?Usuario $usuario, string $origen, array $ia, ?string $placa, ?Vehiculo $vehiculo,
        string $estado, float $monto, ?string $evidencia, $tarifa, ?string $nota, ?string $categoria = null, ?int $ejes = null,
        ?float $saldoAntes = null, ?float $saldoDespues = null): Paso
    {
        return Paso::create([
            'vehiculo_id' => $vehiculo?->id,
            'caseta_id' => $caseta->id,
            'usuario_id' => $usuario?->id,
            'placa_detectada' => $placa,
            'placa_cruda' => isset($ia['placa']['texto_crudo']) ? Str::limit((string) $ia['placa']['texto_crudo'], 20, '') : null,
            'placa_confianza' => $ia['placa']['confianza'] ?? null,
            'formato_valido' => (bool) ($ia['placa']['formato_bolivia'] ?? false),
            'categoria_detectada' => $ia['vehiculo']['categoria'] ?? null,
            'categoria_confianza' => $ia['vehiculo']['confianza'] ?? null,
            'categoria_cobrada' => $categoria,
            'ejes_detectados' => $ia['ejes'] ?? null,
            'ejes_cobrados' => $ejes,
            'vista_vehiculo' => $ia['vista_vehiculo'] ?? null,
            'pasajeros' => $ia['pasajeros']['conteo'] ?? null,
            'pasajeros_confianza' => $ia['pasajeros']['confianza_promedio'] ?? null,
            'tarifa_id' => $tarifa?->id,
            'monto' => $monto,
            'saldo_anterior' => $saldoAntes,
            'saldo_posterior' => $saldoDespues,
            'estado' => $estado,
            'evidencia_path' => $evidencia,
            'latencia_ms' => $ia['latencia_ms'] ?? null,
            'origen' => $origen,
            'nota' => $nota ? Str::limit($nota, 250) : null,
            'respuesta_ia' => $ia ? $this->sinImagen($ia) : null,
        ]);
    }

    private function crearAlerta(Paso $paso, string $tipo, ?string $placa, string $mensaje, string $severidad): Alerta
    {
        $alerta = Alerta::create(['paso_id' => $paso->id, 'caseta_id' => $paso->caseta_id, 'tipo' => $tipo,
            'placa' => $placa, 'mensaje' => $mensaje, 'severidad' => $severidad]);
        $this->emitir(new AlertaCreada($alerta));

        return $alerta;
    }

    private function notificar(Paso $paso): void
    {
        EventoHardware::emitir($paso->estado === 'autorizado' ? 'ok' : 'alerta', [
            'caseta_id' => $paso->caseta_id, 'placa' => $paso->placa_detectada, 'monto' => (float) $paso->monto,
            'paso_id' => $paso->id, 'mensaje' => $paso->estado_texto,
        ]);
        $this->emitir(new PasoRegistrado($paso, EstadisticasService::contadores()));
    }

    /** El tiempo real nunca debe romper el registro del paso (Pusher caído o sin configurar). */
    private function emitir(object $evento): void
    {
        try {
            broadcast($evento);
        } catch (Throwable $e) {
            Log::notice('Broadcast no enviado: '.$e->getMessage());
        }
    }

    /** Guarda SOLO la imagen anotada (pasajeros ya enmascarados por M4). Nunca el frame crudo. */
    private function guardarEvidencia(?string $dataUri): ?string
    {
        if (! $dataUri || ! str_contains($dataUri, ',')) {
            return null;
        }
        $bin = base64_decode(explode(',', $dataUri, 2)[1], true);
        if ($bin === false || strlen($bin) < 100) {
            return null;
        }
        $ruta = 'evidencias/'.now()->format('Y/m/d').'/'.Str::uuid().'.jpg';
        Storage::disk('public')->put($ruta, $bin);

        return $ruta;
    }

    private function sinImagen(array $ia): array
    {
        unset($ia['imagen_anotada_base64']);

        return $ia;
    }
}
