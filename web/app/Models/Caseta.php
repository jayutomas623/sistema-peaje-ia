<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Caseta extends Model
{
    protected $table = 'casetas';

    protected $fillable = ['codigo', 'nombre', 'ubicacion', 'carretera', 'latitud', 'longitud', 'activa'];

    protected function casts(): array
    {
        return ['activa' => 'boolean', 'latitud' => 'float', 'longitud' => 'float'];
    }

    public function pasos(): HasMany
    {
        return $this->hasMany(Paso::class);
    }
}
