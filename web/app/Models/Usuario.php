<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Cuenta de Google en lista blanca. Sin contraseña: solo Google OAuth / ID Token.
 */
class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROL_ADMIN = 'administrador';
    public const ROL_OPERADOR = 'operador';
    public const ROLES = [self::ROL_ADMIN, self::ROL_OPERADOR];

    protected $table = 'usuarios';

    protected $fillable = ['nombre', 'email', 'google_id', 'avatar', 'rol', 'activo', 'ultimo_acceso_at'];

    protected $hidden = ['remember_token', 'google_id'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'ultimo_acceso_at' => 'datetime',
        ];
    }

    public function esAdmin(): bool
    {
        return $this->rol === self::ROL_ADMIN;
    }

    /** Busca un usuario autorizado (lista blanca + activo) por email, sin distinguir mayúsculas. */
    public static function autorizado(?string $email): ?self
    {
        if (! $email) {
            return null;
        }

        return static::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->where('activo', true)->first();
    }

    // Sin contraseña local
    public function getAuthPassword(): string
    {
        return '';
    }
}
