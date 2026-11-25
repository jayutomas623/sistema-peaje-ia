<?php

namespace Database\Seeders;

use App\Models\Caseta;
use Illuminate\Database\Seeder;

class CasetaSeeder extends Seeder
{
    public function run(): void
    {
        $casetas = [
            ['codigo' => 'AUT-01', 'nombre' => 'Peaje Autopista La Paz – El Alto', 'ubicacion' => 'Autopista, km 6',
                'carretera' => 'Autopista La Paz – El Alto', 'latitud' => -16.4955, 'longitud' => -68.1645],
            ['codigo' => 'ACH-02', 'nombre' => 'Peaje Achica Arriba', 'ubicacion' => 'Ruta 1, salida a Oruro',
                'carretera' => 'Ruta Nacional 1 (La Paz – Oruro)', 'latitud' => -16.6280, 'longitud' => -68.2342],
            ['codigo' => 'COT-03', 'nombre' => 'Peaje Cotapata', 'ubicacion' => 'Ruta 3, camino a los Yungas',
                'carretera' => 'Ruta Nacional 3 (La Paz – Yungas)', 'latitud' => -16.3002, 'longitud' => -67.8850],
        ];
        foreach ($casetas as $c) {
            Caseta::updateOrCreate(['codigo' => $c['codigo']], $c + ['activa' => true]);
        }
    }
}
