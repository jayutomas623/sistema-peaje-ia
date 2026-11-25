<?php

namespace App\Services;

use App\Models\Paso;
use App\Models\Vehiculo;

/**
 * Normalización de placas bolivianas y búsqueda difusa para errores típicos de OCR.
 * Formato boliviano: 3 o 4 dígitos + 3 letras  (^\d{3,4}[A-Z]{3}$).
 */
class PlacaService
{
    public const FORMATO = '/^\d{3,4}[A-Z]{3}$/';

    /** Pares que el OCR confunde (indicados en clase: O↔0, I↔1, B↔8, S↔5, 3↔8). */
    public const CONFUSIONES = [
        ['O', '0'], ['Q', '0'], ['D', '0'], ['I', '1'], ['L', '1'], ['T', '1'], ['B', '8'], ['S', '5'],
        ['3', '8'], ['Z', '2'], ['G', '6'], ['A', '4'], ['E', '3'], ['6', '8'], ['U', 'V'], ['K', 'X'],
    ];

    /** Mayúsculas, sin guiones, espacios ni símbolos. */
    public static function normalizar(?string $placa): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $placa)) ?? '';
    }

    public static function formatoValido(?string $placa): bool
    {
        return (bool) preg_match(self::FORMATO, self::normalizar($placa));
    }

    /** Corrige por posición (dígitos primero, letras al final) si así se obtiene el formato boliviano. */
    public static function corregirFormato(?string $placa): string
    {
        $p = self::normalizar($placa);
        if (self::formatoValido($p) || strlen($p) < 6 || strlen($p) > 7) {
            return $p;
        }
        $aDig = ['O' => '0', 'Q' => '0', 'D' => '0', 'I' => '1', 'L' => '1', 'T' => '1', 'Z' => '2', 'S' => '5',
            'B' => '8', 'G' => '6', 'A' => '4', 'E' => '3'];
        $aLet = ['0' => 'O', '1' => 'I', '8' => 'B', '5' => 'S', '2' => 'Z', '6' => 'G', '4' => 'A', '7' => 'T', '3' => 'E'];
        $nDig = strlen($p) - 3;
        $r = '';
        foreach (str_split($p) as $i => $c) {
            $r .= $i < $nDig ? ($aDig[$c] ?? $c) : ($aLet[$c] ?? $c);
        }

        return self::formatoValido($r) ? $r : $p;
    }

    /** Extrae candidatos de placa de un texto libre ("¿pasó la 2481-klm?"). */
    public static function extraerDeTexto(string $texto): array
    {
        $t = mb_strtoupper($texto);
        preg_match_all('/(?<![A-Z0-9])([0-9OIBSZ]{3,4})[\s\-\.]?([A-Z0-9]{3})(?![A-Z0-9])/u', $t, $m, PREG_SET_ORDER);
        $res = [];
        foreach ($m as $g) {
            $cand = self::normalizar($g[1].$g[2]);
            if (preg_match('/\d/', $cand) && preg_match('/[A-Z]/', $cand)) {
                $res[] = $cand;
            }
        }

        return array_values(array_unique($res));
    }

    /** Distancia entre placas: Levenshtein donde una sustitución entre caracteres confundibles cuesta 0.5. */
    public static function distancia(string $a, string $b): float
    {
        $n = strlen($a);
        $m = strlen($b);
        $d = [];
        for ($i = 0; $i <= $n; $i++) {
            $d[$i][0] = $i;
        }
        for ($j = 0; $j <= $m; $j++) {
            $d[0][$j] = $j;
        }
        for ($i = 1; $i <= $n; $i++) {
            for ($j = 1; $j <= $m; $j++) {
                $costo = $a[$i - 1] === $b[$j - 1] ? 0 : (self::confundibles($a[$i - 1], $b[$j - 1]) ? 0.5 : 1);
                $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + $costo);
            }
        }

        return (float) $d[$n][$m];
    }

    public static function confundibles(string $x, string $y): bool
    {
        foreach (self::CONFUSIONES as [$p, $q]) {
            if (($x === $p && $y === $q) || ($x === $q && $y === $p)) {
                return true;
            }
        }

        return false;
    }

    /** ¿Las dos placas solo difieren en caracteres confundibles por el OCR (misma longitud)? */
    public static function equivalenteOcr(string $a, string $b): bool
    {
        if (strlen($a) !== strlen($b) || $a === $b) {
            return $a === $b;
        }
        for ($i = 0; $i < strlen($a); $i++) {
            if ($a[$i] !== $b[$i] && ! self::confundibles($a[$i], $b[$i])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Placas conocidas (vehículos registrados + placas detectadas en pasos) cercanas a la consulta:
     * Levenshtein ≤ 2 (las sustituciones OCR cuentan 0.5). Ordenadas de más a menos parecida.
     *
     * @return array<int, array{placa: string, distancia: float, registrada: bool}>
     */
    public static function similares(string $placa, float $maxDistancia = 2.0, int $limite = 5): array
    {
        $p = self::normalizar($placa);
        if ($p === '') {
            return [];
        }
        $registradas = Vehiculo::pluck('placa')->all();
        $detectadas = Paso::whereNotNull('placa_detectada')->distinct()->pluck('placa_detectada')->all();
        $res = [];
        foreach (array_unique(array_merge($registradas, $detectadas)) as $cand) {
            if ($cand === $p) {
                continue;
            }
            $dist = self::distancia($p, $cand);
            if ($dist <= $maxDistancia) {
                $res[] = ['placa' => $cand, 'distancia' => $dist, 'registrada' => in_array($cand, $registradas, true)];
            }
        }
        usort($res, fn ($x, $y) => [$x['distancia'], ! $x['registrada']] <=> [$y['distancia'], ! $y['registrada']]);

        return array_slice($res, 0, $limite);
    }

    /**
     * Busca el vehículo registrado: coincidencia exacta o, si no existe, un único vehículo
     * cuya placa solo difiere en caracteres confundibles por el OCR (p. ej. 1483AKF -> 1483AXF).
     *
     * @return array{0: ?Vehiculo, 1: ?string} [vehículo, nota de corrección]
     */
    public static function buscarVehiculo(string $placa): array
    {
        $p = self::normalizar($placa);
        if ($p === '') {
            return [null, null];
        }
        if ($v = Vehiculo::where('placa', $p)->first()) {
            return [$v, null];
        }
        $candidatos = Vehiculo::whereRaw('LENGTH(placa) = ?', [strlen($p)])->get()
            ->filter(fn (Vehiculo $v) => self::equivalenteOcr($p, $v->placa));
        if ($candidatos->count() === 1) {
            $v = $candidatos->first();

            return [$v, "Placa corregida por similitud OCR: {$p} → {$v->placa}"];
        }

        return [null, null];
    }
}
