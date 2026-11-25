<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UsuarioSeeder::class,       // docente (administrador) + ADMIN_EMAIL
            CasetaSeeder::class,
            TarifaSeeder::class,        // Bs por categoría y ejes
            ConfiguracionSeeder::class, // INFERENCE_URL / TOKEN editables desde el panel
            VehiculoSeeder::class,      // 22 vehículos bolivianos (fotos reales del curso)
            PasoSeeder::class,          // pasos históricos + alertas con evidencias
        ]);
    }
}
