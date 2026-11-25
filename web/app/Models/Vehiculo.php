<?php

namespace App\Models;

use App\Services\PlacaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Vehiculo extends Model
{
    protected $table = 'vehiculos';

    protected $fillable = [
        'placa', 'propietario', 'ci_nit', 'telefono', 'categoria', 'ejes', 'marca', 'modelo',
        'color', 'anio', 'saldo', 'activo', 'foto_referencia', 'observaciones',
    ];

    protected function casts(): array
    {
        return ['ejes' => 'integer', 'saldo' => 'decimal:2', 'activo' => 'boolean', 'anio' => 'integer'];
    }

    /** La placa siempre se guarda normalizada (mayúsculas, sin guiones ni espacios). */
    public function setPlacaAttribute(?string $valor): void
    {
        $this->attributes['placa'] = PlacaService::normalizar($valor);
    }

    public function pasos(): HasMany
    {
        return $this->hasMany(Paso::class);
    }

    public function ultimoPaso(): HasOne
    {
        return $this->hasOne(Paso::class)->latestOfMany();
    }
}
