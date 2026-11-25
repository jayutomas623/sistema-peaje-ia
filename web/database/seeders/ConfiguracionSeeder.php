<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use App\Models\EventoHardware;
use Illuminate\Database\Seeder;

class ConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        if (! Configuracion::where('clave', 'INFERENCE_URL')->exists()) {
            Configuracion::guardar('INFERENCE_URL', config('peaje.inference.url') ?: null, false,
                'URL del servidor de inferencia (https://<POD_ID>-8000.proxy.runpod.net o túnel de Colab)');
        }
        if (! Configuracion::where('clave', 'INFERENCE_TOKEN')->exists()) {
            Configuracion::guardar('INFERENCE_TOKEN', config('peaje.inference.token') ?: null, true, 'Token X-API-Token (cifrado)');
        }
        if (! Configuracion::where('clave', 'PRIORIDAD_CATEGORIA')->exists()) {
            Configuracion::guardar('PRIORIDAD_CATEGORIA', config('peaje.prioridad_categoria', 'ia'), false,
                'Categoría a cobrar: ia (detectada por M2) | registro (del vehículo)');
        }
        if (! EventoHardware::exists()) {
            EventoHardware::emitir('idle', ['mensaje' => 'Sistema iniciado']);
        }
    }
}
