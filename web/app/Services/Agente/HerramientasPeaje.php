<?php

namespace App\Services\Agente;

use App\Models\Alerta;
use App\Models\Caseta;
use App\Models\Paso;
use App\Models\Tarifa;
use App\Models\Vehiculo;
use App\Services\EstadisticasService;
use App\Services\PlacaService;

/**
 * Herramientas (function calling) del agente. TODAS consultan la BD real: el LLM nunca
 * responde sin pasar por aquí, y el modo determinista usa exactamente las mismas funciones.
 */
class HerramientasPeaje
{
    public const ESTADOS = [
        1 => 'registrada_con_paso',
        2 => 'registrada_sin_paso',
        3 => 'identificada_no_registrada',
        4 => 'no_existe',
    ];

    /** Declaraciones en formato JSON-Schema (compatibles con Gemini y OpenAI). */
    public static function declaraciones(): array
    {
        $placa = ['type' => 'string', 'description' => 'Placa boliviana, p. ej. 2481KLM o 2481-KLM'];

        return [
            ['name' => 'consultar_placa',
                'description' => 'Clasifica una placa en uno de 4 estados (registrada con paso, registrada sin paso, identificada no registrada, no existe) con datos del último paso y sugerencias de placas parecidas.',
                'parameters' => ['type' => 'object', 'properties' => ['placa' => $placa], 'required' => ['placa']]],
            ['name' => 'buscar_placas_similares',
                'description' => 'Busca placas parecidas (errores de OCR: O/0, I/1, B/8, S/5, 3/8; Levenshtein <= 2).',
                'parameters' => ['type' => 'object', 'properties' => ['placa' => $placa], 'required' => ['placa']]],
            ['name' => 'historial_placa',
                'description' => 'Lista los últimos pasos de una placa con fecha, caseta, categoría, ejes, pasajeros, monto y estado.',
                'parameters' => ['type' => 'object', 'properties' => ['placa' => $placa,
                    'limite' => ['type' => 'integer', 'description' => 'Máximo de pasos (1-20)']], 'required' => ['placa']]],
            ['name' => 'resumen_caseta',
                'description' => 'Resumen del día de una caseta (o de todas si no se indica): pasos, recaudación en Bs, alertas, pasajeros.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'caseta' => ['type' => 'string', 'description' => 'ID, código o parte del nombre de la caseta'],
                    'fecha' => ['type' => 'string', 'description' => 'Fecha YYYY-MM-DD (por defecto hoy)']]]],
            ['name' => 'estadisticas_hoy',
                'description' => 'Contadores globales de hoy: pasos, recaudación (Bs), pasajeros, alertas activas.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()]],
            ['name' => 'alertas_activas',
                'description' => 'Alertas no atendidas (evasión, sin saldo, placa ilegible).',
                'parameters' => ['type' => 'object', 'properties' => ['limite' => ['type' => 'integer']]]],
            ['name' => 'listar_tarifas',
                'description' => 'Tarifas vigentes en bolivianos por categoría y número de ejes.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()]],
        ];
    }

    public function ejecutar(string $nombre, array $args): array
    {
        return match ($nombre) {
            'consultar_placa' => $this->consultarPlaca((string) ($args['placa'] ?? '')),
            'buscar_placas_similares' => ['placa' => PlacaService::normalizar($args['placa'] ?? ''),
                'similares' => PlacaService::similares((string) ($args['placa'] ?? ''))],
            'historial_placa' => $this->historial((string) ($args['placa'] ?? ''), (int) ($args['limite'] ?? 10)),
            'resumen_caseta' => $this->resumenCaseta($args['caseta'] ?? null, $args['fecha'] ?? null),
            'estadisticas_hoy' => EstadisticasService::contadores(),
            'alertas_activas' => $this->alertasActivas((int) ($args['limite'] ?? 10)),
            'listar_tarifas' => $this->tarifas(),
            default => ['error' => "Herramienta desconocida: {$nombre}"],
        };
    }

    public function consultarPlaca(string $placa): array
    {
        $p = PlacaService::normalizar($placa);
        if ($p === '') {
            return ['error' => 'Placa vacía'];
        }
        $corregida = PlacaService::corregirFormato($p);
        $vehiculo = Vehiculo::where('placa', $p)->first() ?? Vehiculo::where('placa', $corregida)->first();
        $consultada = $vehiculo->placa ?? $p;

        if ($vehiculo) {
            $total = $vehiculo->pasos()->count();
            $base = [
                'placa' => $vehiculo->placa,
                'formato_bolivia' => PlacaService::formatoValido($vehiculo->placa),
                'vehiculo' => [
                    'propietario' => $vehiculo->propietario, 'categoria' => $vehiculo->categoria, 'ejes' => $vehiculo->ejes,
                    'marca' => $vehiculo->marca, 'modelo' => $vehiculo->modelo, 'color' => $vehiculo->color,
                    'saldo_bs' => (float) $vehiculo->saldo, 'activo' => $vehiculo->activo,
                ],
            ];
            if ($total > 0) {
                $ultimo = $vehiculo->pasos()->with('caseta')->latest()->first();

                return ['estado_codigo' => 1, 'estado' => self::ESTADOS[1], 'total_pasos' => $total,
                    'ultimo_paso' => $this->pasoJson($ultimo)] + $base;
            }

            return ['estado_codigo' => 2, 'estado' => self::ESTADOS[2], 'total_pasos' => 0] + $base;
        }

        $pasos = Paso::with('caseta')->whereIn('placa_detectada', array_unique([$p, $corregida]))->latest()->get();
        $sugerencias = PlacaService::similares($p);
        if ($pasos->isNotEmpty()) {
            return ['estado_codigo' => 3, 'estado' => self::ESTADOS[3], 'placa' => $consultada,
                'formato_bolivia' => PlacaService::formatoValido($consultada),
                'alerta' => 'Placa identificada en casetas pero NO registrada: posible evasión de peaje.',
                'total_detecciones' => $pasos->count(), 'ultimo_paso' => $this->pasoJson($pasos->first()),
                'alertas_pendientes' => Alerta::where('placa', $pasos->first()->placa_detectada)->where('atendida', false)->count(),
                'sugerencias' => $sugerencias];
        }

        return ['estado_codigo' => 4, 'estado' => self::ESTADOS[4], 'placa' => $consultada,
            'formato_bolivia' => PlacaService::formatoValido($consultada), 'sugerencias' => $sugerencias];
    }

    public function historial(string $placa, int $limite = 10): array
    {
        $p = PlacaService::normalizar($placa);
        $limite = max(1, min(20, $limite));
        $pasos = Paso::with('caseta')->where('placa_detectada', $p)
            ->orWhereHas('vehiculo', fn ($q) => $q->where('placa', $p))
            ->latest()->limit($limite)->get();

        return ['placa' => $p, 'pasos' => $pasos->map(fn ($x) => $this->pasoJson($x))->all(),
            'total' => Paso::where('placa_detectada', $p)->count()];
    }

    public function resumenCaseta(mixed $caseta, ?string $fecha): array
    {
        $dia = $fecha && strtotime($fecha) ? date('Y-m-d', strtotime($fecha)) : now()->toDateString();
        $casetas = Caseta::query()
            ->when($caseta !== null && $caseta !== '', function ($q) use ($caseta) {
                $q->where(fn ($w) => $w->where('id', is_numeric($caseta) ? (int) $caseta : 0)
                    ->orWhere('codigo', (string) $caseta)
                    ->orWhereLike('nombre', '%'.$caseta.'%'));   // ILIKE en pgsql
            })->get();
        if ($casetas->isEmpty()) {
            return ['error' => 'No se encontró la caseta indicada', 'casetas_disponibles' => Caseta::pluck('nombre')->all()];
        }

        return ['fecha' => $dia, 'casetas' => $casetas->map(function (Caseta $c) use ($dia) {
            $q = Paso::where('caseta_id', $c->id)->whereDate('created_at', $dia);

            return [
                'id' => $c->id, 'codigo' => $c->codigo, 'nombre' => $c->nombre,
                'pasos' => (clone $q)->count(),
                'autorizados' => (clone $q)->where('estado', 'autorizado')->count(),
                'recaudacion_bs' => round((float) (clone $q)->where('estado', 'autorizado')->sum('monto'), 2),
                'no_registradas' => (clone $q)->where('estado', 'no_registrada')->count(),
                'sin_saldo' => (clone $q)->where('estado', 'sin_saldo')->count(),
                'ilegibles' => (clone $q)->where('estado', 'placa_ilegible')->count(),
                'pasajeros' => (int) (clone $q)->sum('pasajeros'),
                'alertas_activas' => Alerta::where('caseta_id', $c->id)->where('atendida', false)->count(),
            ];
        })->all()];
    }

    public function alertasActivas(int $limite = 10): array
    {
        $a = Alerta::with('caseta')->where('atendida', false)->latest()->limit(max(1, min(30, $limite)))->get();

        return ['total' => Alerta::where('atendida', false)->count(), 'alertas' => $a->map(fn ($x) => [
            'tipo' => $x->tipo, 'placa' => $x->placa, 'mensaje' => $x->mensaje, 'caseta' => $x->caseta?->nombre,
            'fecha_hora' => $x->created_at?->format('d/m/Y H:i')])->all()];
    }

    public function tarifas(): array
    {
        return ['moneda' => 'Bs', 'tarifas' => Tarifa::where('activa', true)->orderBy('categoria')->orderBy('ejes')->get()
            ->map(fn ($t) => ['categoria' => $t->categoria, 'ejes' => $t->ejes_texto, 'monto_bs' => (float) $t->monto])->all()];
    }

    private function pasoJson(?Paso $p): ?array
    {
        if (! $p) {
            return null;
        }

        return [
            'fecha_hora' => $p->created_at?->format('d/m/Y H:i:s'),
            'caseta' => $p->caseta?->nombre,
            'categoria' => $p->categoria_cobrada ?? $p->categoria_detectada,
            'ejes' => $p->ejes_cobrados ?? $p->ejes_detectados,
            'pasajeros' => $p->pasajeros,
            'monto_bs' => (float) $p->monto,
            'estado' => $p->estado,
        ];
    }
}
