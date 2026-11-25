<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alerta extends Model
{
    public const TIPOS = [
        'no_registrada' => 'Placa no registrada · posible evasión',
        'sin_saldo' => 'Vehículo sin saldo',
        'placa_ilegible' => 'Placa ilegible',
        'error_inferencia' => 'Error del servidor de inferencia',
    ];

    protected $table = 'alertas';

    protected $fillable = ['paso_id', 'caseta_id', 'tipo', 'placa', 'mensaje', 'severidad', 'atendida',
        'atendida_por', 'atendida_at', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['atendida' => 'boolean', 'atendida_at' => 'datetime'];
    }

    public function paso(): BelongsTo
    {
        return $this->belongsTo(Paso::class);
    }

    public function caseta(): BelongsTo
    {
        return $this->belongsTo(Caseta::class);
    }

    public function atendidaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'atendida_por');
    }

    public function resumen(): array
    {
        $this->loadMissing('caseta:id,nombre', 'paso:id,evidencia_path,monto');

        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'tipo_texto' => self::TIPOS[$this->tipo] ?? $this->tipo,
            'placa' => $this->placa,
            'mensaje' => $this->mensaje,
            'severidad' => $this->severidad,
            'caseta' => $this->caseta?->nombre,
            'paso_id' => $this->paso_id,
            'evidencia_url' => $this->paso?->evidencia_url,
            'atendida' => $this->atendida,
            'fecha' => $this->created_at?->toIso8601String(),
            'hora' => $this->created_at?->format('H:i:s'),
        ];
    }
}
