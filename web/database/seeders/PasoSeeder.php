<?php

namespace Database\Seeders;

use App\Models\Alerta;
use App\Models\Caseta;
use App\Models\Paso;
use App\Models\Usuario;
use App\Models\Vehiculo;
use App\Services\TarifaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Pasos históricos con las fotos reales del curso como primeras evidencias del sistema.
 * Cubre los estados: autorizado, sin_saldo, no_registrada (posible evasión) y placa_ilegible.
 */
class PasoSeeder extends Seeder
{
    /**
     * [placa|null, caseta, cuándo, foto, estado, categoria, conf, ejes, vista, pasajeros]
     * cuándo: int = minutos atrás (hoy) | 'Nd HH:MM' = hace N días a esa hora.
     */
    public const PASOS = [
        ['2481KLM', 'ACH-02', 290, null, 'autorizado', 'camion', 0.88, 3, 'lateral', 1],
        ['1985LNH', 'AUT-01', 250, '08_1985LNH.jpg', 'autorizado', 'minibus', 0.84, null, 'frontal/posterior', 9],
        ['1151DKL', 'AUT-01', 215, '01_1151DKL.jpg', 'autorizado', 'auto', 0.91, null, 'frontal/posterior', 1],
        ['1231BTN', 'AUT-01', 190, '02_1231BTN.jpg', 'autorizado', 'auto', 0.89, null, 'frontal/posterior', 1],
        ['6652ZNC', 'ACH-02', 170, '22_6652ZNC.jpg', 'sin_saldo', 'camion', 0.86, null, 'frontal/posterior', 1],
        ['5312FAE', 'ACH-02', 150, '05_5312FAE.jpg', 'autorizado', 'auto', 0.90, null, 'frontal/posterior', 1],
        ['1917YBP', 'AUT-01', 130, '09_1917YBP.jpg', 'no_registrada', 'auto', 0.87, null, 'frontal/posterior', 1],
        ['1078LPS', 'AUT-01', 110, '10_1078LPS.jpg', 'autorizado', 'auto', 0.92, null, 'frontal/posterior', 2],
        [null, 'AUT-01', 95, '03_ilegible_1.jpg', 'placa_ilegible', 'minibus', 0.71, null, 'desconocida', null],
        ['1534PHK', 'ACH-02', 70, '12_1534PHK.jpg', 'autorizado', 'minibus', 0.83, null, 'frontal/posterior', 2],
        ['1640SSU', 'AUT-01', 45, '15_1640SSU.jpg', 'autorizado', 'auto', 0.90, null, 'frontal/posterior', 1],
        ['1703XFY', 'AUT-01', 20, '20_1703XFY.jpg', 'sin_saldo', 'auto', 0.88, null, 'frontal/posterior', 1],
        // días anteriores
        ['1985LNH', 'AUT-01', '1d 07:05', '08_1985LNH.jpg', 'autorizado', 'minibus', 0.85, null, 'frontal/posterior', 12],
        ['1701NAC', 'AUT-01', '1d 12:15', '04_1701NAC.jpg', 'autorizado', 'auto', 0.89, null, 'frontal/posterior', 1],
        ['1985CXX', 'ACH-02', '1d 05:40', '14_1985CXX.jpg', 'autorizado', 'camion', 0.86, null, 'frontal/posterior', 1],
        ['6248RFY', 'COT-03', '1d 15:05', '13_6248RFY.jpg', 'no_registrada', 'auto', 0.90, null, 'frontal/posterior', 1],
        ['4472IUU', 'AUT-01', '1d 17:22', '17_4472IUU.jpg', 'sin_saldo', 'minibus', 0.80, null, 'frontal/posterior', 7],
        ['1151DKL', 'ACH-02', '2d 18:10', '01_1151DKL.jpg', 'autorizado', 'auto', 0.91, null, 'frontal/posterior', 2],
        ['4472ASC', 'COT-03', '2d 11:11', '11_4472ASC.jpg', 'autorizado', 'auto', 0.88, null, 'frontal/posterior', 1],
        [null, 'COT-03', '2d 19:02', '07_ilegible_2.jpg', 'placa_ilegible', 'auto', 0.82, null, 'desconocida', null],
        ['1483AXF', 'AUT-01', '3d 16:45', '06_1483AXF.jpg', 'autorizado', 'auto', 0.90, null, 'frontal/posterior', 1],
        ['6480ZDE', 'ACH-02', '3d 14:20', '14_1985CXX.jpg', 'no_registrada', 'auto', 0.79, null, 'frontal/posterior', 1],
        ['3820HTD', 'COT-03', '4d 20:30', null, 'autorizado', 'bus', 0.93, 2, 'lateral', 31],
        ['1231BTN', 'COT-03', '5d 09:30', '02_1231BTN.jpg', 'autorizado', 'auto', 0.90, 2, '3/4', 1],
    ];

    public function run(): void
    {
        if (Paso::where('origen', 'seeder')->exists()) {
            return;   // idempotente
        }
        $casetas = Caseta::pluck('id', 'codigo');
        $docente = Usuario::where('email', config('peaje.docente_email'))->first();

        // Orden cronológico para reconstruir saldos (el saldo final es el del VehiculoSeeder)
        $filas = collect(self::PASOS)->map(fn ($f) => $f + ['fecha' => $this->fecha($f[2])])->sortBy('fecha')->values();
        $gastado = [];
        foreach ($filas as $f) {
            if ($f[4] === 'autorizado' && $f[0]) {
                $tarifa = TarifaService::obtener($f[5], $f[7] ?? Vehiculo::where('placa', $f[0])->value('ejes'));
                $gastado[$f[0]] = ($gastado[$f[0]] ?? 0) + (float) $tarifa?->monto;
            }
        }
        $saldos = [];
        foreach ($filas as $f) {
            [$placa, $codigo, , $foto, $estado, $cat, $conf, $ejesDet, $vista, $pasajeros] = $f;
            $vehiculo = $placa ? Vehiculo::where('placa', $placa)->first() : null;
            $ejes = $ejesDet ?? $vehiculo?->ejes ?? 2;
            $tarifa = $placa ? TarifaService::obtener($cat, $ejes) : null;
            $monto = (float) ($tarifa?->monto ?? 0);
            $antes = $despues = null;
            if ($vehiculo) {
                $saldos[$placa] ??= (float) $vehiculo->saldo + ($gastado[$placa] ?? 0);
                $antes = $saldos[$placa];
                $despues = $estado === 'autorizado' ? round($antes - $monto, 2) : $antes;
                $saldos[$placa] = $despues;
            }
            $evidencia = $foto ? VehiculoSeeder::copiarFoto($foto) : null;
            $paso = Paso::create([
                'vehiculo_id' => $vehiculo?->id,
                'caseta_id' => $casetas[$codigo],
                'usuario_id' => $docente?->id,
                'placa_detectada' => $placa,
                'placa_cruda' => $placa,
                'placa_confianza' => $placa ? round($conf - 0.03, 3) : 0.31,
                'formato_valido' => (bool) $placa,
                'categoria_detectada' => $cat,
                'categoria_confianza' => $conf,
                'categoria_cobrada' => $placa ? $cat : null,
                'ejes_detectados' => $ejesDet,
                'ejes_cobrados' => $placa ? $ejes : null,
                'vista_vehiculo' => $vista,
                'pasajeros' => $pasajeros,
                'pasajeros_confianza' => $pasajeros ? 0.81 : null,
                'tarifa_id' => $tarifa?->id,
                'monto' => $placa ? $monto : 0,
                'saldo_anterior' => $antes,
                'saldo_posterior' => $despues,
                'estado' => $estado,
                'evidencia_path' => $evidencia,
                'latencia_ms' => random_int(140, 420),
                'origen' => 'seeder',
                'nota' => $foto ? 'Evidencia inicial: foto real del curso' : null,
                'created_at' => $f['fecha'],
                'updated_at' => $f['fecha'],
            ]);

            $tipo = match ($estado) {
                'sin_saldo' => 'sin_saldo', 'no_registrada' => 'no_registrada', 'placa_ilegible' => 'placa_ilegible', default => null,
            };
            if ($tipo) {
                $antigua = $f['fecha']->lt(now()->subDays(2));
                Alerta::create([
                    'paso_id' => $paso->id, 'caseta_id' => $paso->caseta_id, 'tipo' => $tipo, 'placa' => $placa,
                    'mensaje' => match ($tipo) {
                        'sin_saldo' => "Placa {$placa} sin saldo suficiente (requiere Bs ".number_format($monto, 2).').',
                        'no_registrada' => "Placa {$placa} identificada pero NO registrada: posible evasión.",
                        default => 'Placa ilegible. Verificar manualmente.',
                    },
                    'severidad' => $tipo === 'placa_ilegible' ? 'media' : 'alta',
                    'atendida' => $antigua, 'atendida_por' => $antigua ? $docente?->id : null,
                    'atendida_at' => $antigua ? $f['fecha']->copy()->addMinutes(15) : null,
                    'created_at' => $f['fecha'], 'updated_at' => $f['fecha'],
                ]);
            }
        }
    }

    private function fecha(int|string $cuando): Carbon
    {
        if (is_int($cuando)) {
            return now()->subMinutes($cuando)->startOfMinute();
        }
        [$d, $hm] = explode(' ', $cuando);
        [$h, $m] = explode(':', $hm);

        return now()->subDays((int) $d)->setTime((int) $h, (int) $m);
    }
}
