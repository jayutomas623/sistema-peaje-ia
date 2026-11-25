<?php

namespace App\Console\Commands;

use App\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * php artisan peaje:sembrar-si-vacio
 *
 * Usado por docker-entrypoint.sh en cada arranque (Render, Docker):
 *   - Si la tabla `usuarios` está vacía → ejecuta los seeders (primer despliegue).
 *   - Si ya hay datos → NO toca la BD (no duplica ni borra nada).
 *   - En ambos casos repone las fotos semilla en el disco público: el disco de Render (plan gratuito)
 *     es efímero y se borra en cada redeploy, mientras que la BD PostgreSQL persiste.
 */
class SembrarSiVacio extends Command
{
    protected $signature = 'peaje:sembrar-si-vacio';

    protected $description = 'Ejecuta los seeders solo si la tabla usuarios está vacía y repone las fotos semilla';

    public function handle(): int
    {
        $total = Usuario::count();
        if ($total === 0) {
            $this->info('Tabla usuarios vacía: ejecutando seeders…');
            $this->call('db:seed', ['--force' => true]);
        } else {
            $this->info("La tabla usuarios ya tiene {$total} registro(s): se omiten los seeders.");
        }

        $repuestas = 0;
        foreach (File::glob(database_path('seeders/evidencias/*.jpg')) as $origen) {
            $destino = 'evidencias/seed/'.basename($origen);
            if (! Storage::disk('public')->exists($destino)) {
                Storage::disk('public')->put($destino, File::get($origen));
                $repuestas++;
            }
        }
        $this->line("Fotos semilla repuestas en el disco público: {$repuestas}");

        return self::SUCCESS;
    }
}
