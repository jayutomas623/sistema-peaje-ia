<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Paso extends Model
{
    public const ESTADOS = ['autorizado', 'sin_saldo', 'no_registrada', 'placa_ilegible', 'error_inferencia'];

    public const ETIQUETAS_ESTADO = [
        'autorizado' => 'Autorizado',
        'sin_saldo' => 'Sin saldo',
        'no_registrada' => 'No registrada (posible evasión)',
        'placa_ilegible' => 'Placa ilegible',
        'error_inferencia' => 'Error de inferencia',
    ];

    protected $table = 'pasos';

    protected $fillable = [
        'vehiculo_id', 'caseta_id', 'usuario_id', 'placa_detectada', 'placa_cruda', 'placa_confianza',
        'formato_valido', 'categoria_detectada', 'categoria_confianza', 'categoria_cobrada', 'ejes_detectados',
        'ejes_cobrados', 'vista_vehiculo', 'pasajeros', 'pasajeros_confianza', 'tarifa_id', 'monto',
        'saldo_anterior', 'saldo_posterior', 'estado', 'evidencia_path', 'latencia_ms', 'origen', 'nota',
        'respuesta_ia', 'created_at', 'updated_at',
    ];

    protected $appends = ['evidencia_url'];

    protected function casts(): array
    {
        return [
            'formato_valido' => 'boolean',
            'placa_confianza' => 'float',
            'categoria_confianza' => 'float',
            'pasajeros_confianza' => 'float',
            'monto' => 'decimal:2',
            'saldo_anterior' => 'decimal:2',
            'saldo_posterior' => 'decimal:2',
            'latencia_ms' => 'float',
            'respuesta_ia' => 'array',
        ];
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function caseta(): BelongsTo
    {
        return $this->belongsTo(Caseta::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function tarifa(): BelongsTo
    {
        return $this->belongsTo(Tarifa::class);
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(Alerta::class);
    }

    public function getEvidenciaUrlAttribute(): ?string
    {
        return $this->evidencia_path ? Storage::disk('public')->url($this->evidencia_path) : null;
    }

    public function getEstadoTextoAttribute(): string
    {
        return self::ETIQUETAS_ESTADO[$this->estado] ?? $this->estado;
    }

    /** Representación ligera para tiempo real, móvil y panel. */
    public function resumen(): array
    {
        $this->loadMissing('caseta:id,nombre,codigo', 'vehiculo:id,placa,propietario,saldo');

        return [
            'id' => $this->id,
            'placa' => $this->placa_detectada,
            'placa_cruda' => $this->placa_cruda,
            'placa_confianza' => $this->placa_confianza,
            'registrado' => (bool) $this->vehiculo_id,
            'propietario' => $this->vehiculo?->propietario,
            'saldo' => $this->vehiculo ? (float) $this->vehiculo->saldo : null,
            'caseta' => $this->caseta?->nombre,
            'caseta_id' => $this->caseta_id,
            'categoria' => $this->categoria_cobrada ?? $this->categoria_detectada,
            'categoria_detectada' => $this->categoria_detectada,
            'ejes' => $this->ejes_cobrados ?? $this->ejes_detectados,
            'ejes_detectados' => $this->ejes_detectados,
            'vista_vehiculo' => $this->vista_vehiculo,
            'pasajeros' => $this->pasajeros,
            'monto' => (float) $this->monto,
            'estado' => $this->estado,
            'estado_texto' => $this->estado_texto,
            'evidencia_url' => $this->evidencia_url,
            'latencia_ms' => $this->latencia_ms,
            'origen' => $this->origen,
            'nota' => $this->nota,
            'fecha' => $this->created_at?->toIso8601String(),
            'hora' => $this->created_at?->format('H:i:s'),
        ];
    }
}
