<?php

namespace Database\Seeders;

use App\Models\Tarifa;
use Illuminate\Database\Seeder;

/**
 * Tarifas en bolivianos (Bs) por categoría y ejes. Mientras más ejes, más desgaste del asfalto
 * y mayor tarifa (no existen vehículos de 1 eje; 6 = "6 o más ejes").
 */
class TarifaSeeder extends Seeder
{
    public const ESCALA = [
        // categoria => [base 2 ejes, incremento por eje adicional]
        'moto' => [3.00, 0],
        'auto' => [6.00, 3.00],
        'minibus' => [8.00, 4.00],
        'bus' => [15.00, 6.00],
        'camion' => [15.00, 10.00],
    ];

    public function run(): void
    {
        foreach (self::ESCALA as $categoria => [$base, $inc]) {
            foreach ($categoria === 'moto' ? [2] : [2, 3, 4, 5, 6] as $ejes) {
                Tarifa::updateOrCreate(['categoria' => $categoria, 'ejes' => $ejes], [
                    'monto' => $base + ($ejes - 2) * $inc,
                    'descripcion' => Tarifa::ETIQUETAS[$categoria].' · '.($ejes >= 6 ? '6 o más' : $ejes).' ejes',
                    'activa' => true,
                ]);
            }
        }
    }
}
