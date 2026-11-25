<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cola de señales para el ESP32 (LED azul = inferencia, verde = ok, rojo = alerta).
 */
class EventoHardware extends Model
{
    protected $table = 'eventos_hardware';

    protected $fillable = ['tipo_senal', 'placa', 'monto', 'paso_id', 'caseta_id', 'mensaje'];

    protected function casts(): array
    {
        return ['monto' => 'float'];
    }

    public static function emitir(string $tipo, array $datos = []): self
    {
        $evento = static::create(['tipo_senal' => $tipo] + $datos);
        // Se conservan solo los últimos 500 eventos
        if ($evento->id % 50 === 0) {
            static::where('id', '<', $evento->id - 500)->delete();
        }

        return $evento;
    }
}
