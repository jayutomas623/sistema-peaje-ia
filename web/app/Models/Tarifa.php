<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tarifa extends Model
{
    public const CATEGORIAS = ['moto', 'auto', 'minibus', 'bus', 'camion'];

    public const ETIQUETAS = [
        'moto' => 'Motocicleta', 'auto' => 'Auto particular', 'minibus' => 'Minibús',
        'bus' => 'Bus', 'camion' => 'Camión',
    ];

    protected $table = 'tarifas';

    protected $fillable = ['categoria', 'ejes', 'monto', 'descripcion', 'activa'];

    protected function casts(): array
    {
        return ['ejes' => 'integer', 'monto' => 'decimal:2', 'activa' => 'boolean'];
    }

    public function getEjesTextoAttribute(): string
    {
        return $this->ejes >= 6 ? '6+' : (string) $this->ejes;
    }
}
