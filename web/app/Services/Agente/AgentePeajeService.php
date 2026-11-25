<?php

namespace App\Services\Agente;

use App\Models\Usuario;
use App\Services\Agente\Proveedores\GeminiProveedor;
use App\Services\Agente\Proveedores\OpenAiCompatibleProveedor;
use App\Services\Agente\Proveedores\ProveedorLlm;
use App\Services\InferenciaClient;
use App\Services\PlacaService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Agente conversacional de apoyo al operador.
 *  - Solo responde con datos de la BD (herramientas). Cero alucinaciones.
 *  - Clasifica placas en 4 estados exactos y sugiere "¿Quisiste decir …?" ante errores de OCR.
 *  - Acepta texto y/o imagen (lee la placa con INFERENCE_URL/inferir-placa).
 *  - LLM configurable (Gemini por defecto, OpenAI/Groq/Ollama) con respaldo determinista.
 */
class AgentePeajeService
{
    public const SISTEMA = <<<'TXT'
Eres "Agente Peaje", asistente del operador de un sistema de peaje en Bolivia. Responde SIEMPRE en español, breve y claro.
REGLAS ESTRICTAS (no negociables):
1. Solo puedes afirmar datos que provengan de las herramientas o del bloque DATOS_VERIFICADOS. Nunca inventes placas, fechas, montos, casetas ni propietarios.
2. Para cualquier pregunta sobre una placa usa consultar_placa. Normaliza la placa (mayúsculas, sin guiones ni espacios).
3. Informa el estado exacto con estas etiquetas:
   - estado 1 "Registrada y con paso": incluye fecha/hora, caseta, categoría, ejes y monto (Bs) del último paso.
   - estado 2 "Registrada sin paso".
   - estado 3 "Identificada no registrada": advierte ALERTA de posible evasión de peaje.
   - estado 4 "No existe" en el sistema.
4. Si hay sugerencias de placas parecidas (errores de OCR como O/0, I/1, B/8, S/5, 3/8), pregunta literalmente: "¿Quisiste decir <PLACA>?".
5. Si la información no está en las herramientas, di que no la tienes. Montos siempre en bolivianos (Bs).
6. No reveles datos personales sensibles más allá del nombre del propietario, ni hagas reconocimiento de personas.
TXT;

    public function __construct(private HerramientasPeaje $herramientas, private InferenciaClient $inferencia)
    {
    }

    /**
     * @param  array<int, array{rol: string, texto: string}>  $historial
     */
    public function responder(?string $mensaje, ?string $imagen, ?Usuario $usuario = null, array $historial = []): array
    {
        $mensaje = trim((string) $mensaje);
        $placasImagen = [];
        $lecturaImagen = null;

        // 1) Imagen: se lee la placa con M1 (servidor de inferencia)
        if ($imagen !== null) {
            try {
                $lecturaImagen = $this->inferencia->inferirPlaca($imagen);
            } catch (Throwable $e) {
                return $this->respuesta('No pude analizar la imagen: el servidor de inferencia no respondió ('.$e->getMessage().'). Intenta de nuevo o escribe la placa.',
                    'error_inferencia', [], 'determinista');
            }
            $texto = $lecturaImagen['placa']['texto'] ?? null;
            if (! ($lecturaImagen['legible'] ?? false) || ! $texto) {
                $crudo = $lecturaImagen['placa']['texto_crudo'] ?? null;

                return $this->respuesta('No encontré una placa legible en la imagen'.($crudo ? " (lectura parcial: {$crudo})" : '')
                    .'. Por favor envía otra foto más cercana, de frente y con buena iluminación.', 'imagen_sin_placa',
                    ['lectura' => $this->sinBase64($lecturaImagen)], 'determinista', $lecturaImagen['imagen_anotada_base64'] ?? null);
            }
            $placasImagen = [PlacaService::normalizar($texto)];
        }

        // 2) Placas mencionadas en el texto
        $placas = array_values(array_unique(array_merge($placasImagen, PlacaService::extraerDeTexto($mensaje))));
        $datos = [];
        foreach (array_slice($placas, 0, 3) as $p) {
            $datos['consultar_placa:'.$p] = $this->herramientas->consultarPlaca($p);
        }
        if ($lecturaImagen) {
            $datos['lectura_imagen'] = $this->sinBase64($lecturaImagen);
        }
        if ($mensaje === '' && ! $placas) {
            return $this->respuesta($this->ayuda(), 'ayuda', [], 'determinista');
        }

        // 3) LLM con function calling (si hay proveedor), con respaldo determinista
        $proveedor = $this->proveedor();
        $fuente = 'determinista';
        $texto = null;
        if ($proveedor) {
            try {
                $usuarioTxt = ($mensaje !== '' ? $mensaje : 'Consulta la placa de la imagen.')
                    .($placasImagen ? "\n(Placa leída de la imagen por OCR: {$placasImagen[0]})" : '')
                    .($datos ? "\n\nDATOS_VERIFICADOS (de la BD):\n".json_encode($datos, JSON_UNESCAPED_UNICODE) : '');
                $mensajes = array_merge(array_slice($this->limpiarHistorial($historial), -6), [['rol' => 'usuario', 'texto' => $usuarioTxt]]);
                $texto = $proveedor->responder(self::SISTEMA, $mensajes, HerramientasPeaje::declaraciones(),
                    function (string $nombre, array $args) use (&$datos) {
                        $r = $this->herramientas->ejecutar($nombre, $args);
                        $datos[$nombre.':'.($args['placa'] ?? $args['caseta'] ?? '')] = $r;

                        return $r;
                    });
                $fuente = 'llm:'.$proveedor->nombre();
            } catch (Throwable $e) {
                Log::warning('Agente LLM falló, se usa respaldo determinista: '.$e->getMessage());
                $texto = null;
            }
        }
        if ($texto === null) {
            [$texto, $datos] = $this->determinista($mensaje, $placas, $datos);
        }

        $principal = $this->principal($datos);
        $texto = $this->garantizarSugerencia($texto, $principal);

        return $this->respuesta($texto, $principal['estado'] ?? 'informativo', $datos, $fuente,
            $lecturaImagen['imagen_anotada_base64'] ?? null, $principal);
    }

    /** Respuesta estructurada sin LLM (misma información, redactada con plantillas). */
    private function determinista(string $mensaje, array $placas, array $datos): array
    {
        $m = mb_strtolower($mensaje);
        if ($placas) {
            $partes = [];
            foreach ($datos as $clave => $d) {
                if (str_starts_with($clave, 'consultar_placa:')) {
                    $partes[] = $this->redactarPlaca($d);
                }
            }

            return [implode("\n\n", $partes), $datos];
        }
        if (preg_match('/alerta|evasi|pendiente/u', $m)) {
            $d = $datos['alertas_activas'] = $this->herramientas->alertasActivas(5);
            $lineas = array_map(fn ($a) => "• {$a['fecha_hora']} · {$a['caseta']} · ".($a['placa'] ?? 'sin placa')." — {$a['mensaje']}", $d['alertas']);

            return ["Hay {$d['total']} alertas activas.".($lineas ? "\n".implode("\n", $lineas) : ''), $datos];
        }
        if (preg_match('/tarifa|cu[aá]nto cuesta|precio/u', $m)) {
            $d = $datos['listar_tarifas'] = $this->herramientas->tarifas();
            $lineas = array_map(fn ($t) => "• {$t['categoria']} {$t['ejes']} ejes: Bs ".number_format($t['monto_bs'], 2), $d['tarifas']);

            return ["Tarifas vigentes:\n".implode("\n", $lineas), $datos];
        }
        if (preg_match('/caseta|resumen|recaud|hoy|cu[aá]nt|pasajer|estad[ií]stica/u', $m)) {
            $d = $datos['resumen_caseta'] = $this->herramientas->resumenCaseta(null, null);
            $lineas = array_map(fn ($c) => "• {$c['nombre']}: {$c['pasos']} pasos, Bs ".number_format($c['recaudacion_bs'], 2)
                .", {$c['no_registradas']} no registradas, {$c['pasajeros']} pasajeros, {$c['alertas_activas']} alertas activas", $d['casetas'] ?? []);

            return ["Resumen del {$d['fecha']}:\n".implode("\n", $lineas), $datos];
        }

        return [$this->ayuda(), $datos];
    }

    public function redactarPlaca(array $d): string
    {
        if (isset($d['error'])) {
            return $d['error'];
        }
        $p = $d['placa'];
        $u = $d['ultimo_paso'] ?? null;
        $detalle = $u ? "{$u['fecha_hora']} en {$u['caseta']}, categoría {$u['categoria']}, {$u['ejes']} ejes, monto Bs "
            .number_format($u['monto_bs'], 2).($u['pasajeros'] !== null ? ", {$u['pasajeros']} pasajero(s)" : '') : '';

        $txt = match ($d['estado_codigo']) {
            1 => "✅ Placa {$p}: REGISTRADA Y CON PASO. Último paso: {$detalle}. Total de pasos: {$d['total_pasos']}. "
                ."Propietario: {$d['vehiculo']['propietario']} · saldo Bs ".number_format($d['vehiculo']['saldo_bs'], 2).'.',
            2 => "🟦 Placa {$p}: REGISTRADA SIN PASO. Vehículo {$d['vehiculo']['categoria']} de {$d['vehiculo']['ejes']} ejes "
                ."({$d['vehiculo']['marca']} {$d['vehiculo']['modelo']}), saldo Bs ".number_format($d['vehiculo']['saldo_bs'], 2).'. Aún no registra pasos.',
            3 => "🚨 Placa {$p}: IDENTIFICADA NO REGISTRADA — ALERTA de posible evasión. Detectada {$d['total_detecciones']} vez/veces; "
                ."última: {$detalle}.",
            default => "❌ Placa {$p}: NO EXISTE en el sistema (ni registrada ni detectada en casetas).",
        };
        if (in_array($d['estado_codigo'], [3, 4], true) && ! empty($d['sugerencias'])) {
            $txt .= ' ¿Quisiste decir '.$d['sugerencias'][0]['placa'].'?';
        }

        return $txt;
    }

    /** Si la BD sugiere una placa y el LLM no la mencionó, se agrega la pregunta literal. */
    private function garantizarSugerencia(string $texto, ?array $principal): string
    {
        $sug = $principal['sugerencias'][0]['placa'] ?? null;
        if ($sug && in_array($principal['estado_codigo'] ?? 0, [3, 4], true) && ! str_contains($texto, $sug)) {
            $texto .= "\n\n¿Quisiste decir {$sug}?";
        }

        return $texto;
    }

    private function principal(array $datos): ?array
    {
        foreach ($datos as $clave => $d) {
            if (str_starts_with($clave, 'consultar_placa:') && isset($d['estado_codigo'])) {
                return $d;
            }
        }

        return null;
    }

    private function proveedor(): ?ProveedorLlm
    {
        $cfg = config('peaje.agente');
        $p = $cfg['proveedor'] ?? 'ninguno';
        $t = (int) ($cfg['timeout'] ?? 25);

        return match (true) {
            $p === 'gemini' && ! empty($cfg['gemini']['api_key']) => new GeminiProveedor(
                $cfg['gemini']['api_key'], $cfg['gemini']['modelo'], $cfg['gemini']['base_url'], $t),
            in_array($p, ['openai', 'groq', 'ollama'], true) && ! empty($cfg[$p]['base_url'])
                && ($p === 'ollama' || ! empty($cfg[$p]['api_key'])) => new OpenAiCompatibleProveedor(
                    $p, $cfg[$p]['api_key'] ?? null, $cfg[$p]['modelo'], $cfg[$p]['base_url'], $t),
            default => null,
        };
    }

    private function ayuda(): string
    {
        return "Hola, soy el Agente Peaje. Puedo:\n• Consultar una placa (ej.: \"¿pasó la 2481KLM?\") y decirte si está registrada, si pasó, o si es posible evasión.\n"
            ."• Leer la placa de una foto que me envíes.\n• Dar el resumen de hoy por caseta, recaudación (Bs), pasajeros y alertas activas.\n• Mostrar las tarifas vigentes.";
    }

    private function limpiarHistorial(array $h): array
    {
        return array_values(array_filter(array_map(fn ($m) => [
            'rol' => ($m['rol'] ?? '') === 'agente' ? 'agente' : 'usuario',
            'texto' => mb_substr(trim((string) ($m['texto'] ?? '')), 0, 1500),
        ], $h), fn ($m) => $m['texto'] !== ''));
    }

    private function sinBase64(?array $r): ?array
    {
        if ($r) {
            unset($r['imagen_anotada_base64']);
        }

        return $r;
    }

    private function respuesta(string $texto, string $estado, array $datos, string $fuente, ?string $imagen = null, ?array $principal = null): array
    {
        return [
            'respuesta' => $texto,
            'estado' => $estado,
            'estado_codigo' => $principal['estado_codigo'] ?? null,
            'placa' => $principal['placa'] ?? null,
            'sugerencias' => array_column($principal['sugerencias'] ?? [], 'placa'),
            'datos' => $datos,
            'fuente' => $fuente,
            'imagen_anotada' => $imagen,
        ];
    }
}
