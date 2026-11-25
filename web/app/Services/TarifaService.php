<?php

namespace App\Services;

use App\Models\Tarifa;

class TarifaService
{
    /** Tarifa por categoría y ejes (mínimo 2 ejes; 6 = "6 o más"). */
    public static function obtener(string $categoria, ?int $ejes): ?Tarifa
    {
        $categoria = in_array($categoria, Tarifa::CATEGORIAS, true) ? $categoria : 'auto';
        $ejes = max(2, min(6, (int) ($ejes ?: 2)));

        return Tarifa::where('categoria', $categoria)->where('activa', true)->where('ejes', $ejes)->first()
            ?? Tarifa::where('categoria', $categoria)->where('activa', true)->where('ejes', '<=', $ejes)
                ->orderByDesc('ejes')->first()
            ?? Tarifa::where('categoria', $categoria)->where('activa', true)->orderBy('ejes')->first();
    }
}
