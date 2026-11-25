<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Configuración dinámica editable desde el panel (sin redeploy): INFERENCE_URL, INFERENCE_TOKEN, etc.
 * Los valores secretos se guardan cifrados con APP_KEY.
 */
class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = ['clave', 'valor', 'es_secreto', 'descripcion'];

    protected $hidden = ['valor'];

    protected function casts(): array
    {
        return ['es_secreto' => 'boolean'];
    }

    public static function obtener(string $clave, mixed $defecto = null): mixed
    {
        try {
            $fila = Cache::remember("config:$clave", 60, function () use ($clave) {
                if (! Schema::hasTable('configuraciones')) {
                    return null;
                }
                $c = static::where('clave', $clave)->first();

                return $c ? ['v' => $c->valor, 's' => $c->es_secreto] : null;
            });
        } catch (Throwable) {
            return $defecto;
        }
        if (! $fila || $fila['v'] === null || $fila['v'] === '') {
            return $defecto;
        }
        if ($fila['s']) {
            try {
                return Crypt::decryptString($fila['v']);
            } catch (Throwable) {
                return $defecto;
            }
        }

        return $fila['v'];
    }

    public static function guardar(string $clave, ?string $valor, bool $secreto = false, ?string $descripcion = null): void
    {
        $datos = ['valor' => $valor === null || $valor === '' ? null : ($secreto ? Crypt::encryptString($valor) : $valor),
            'es_secreto' => $secreto];
        if ($descripcion !== null) {
            $datos['descripcion'] = $descripcion;
        }
        static::updateOrCreate(['clave' => $clave], $datos);
        Cache::forget("config:$clave");
    }
}
