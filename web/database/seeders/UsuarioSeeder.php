<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;

/** Lista blanca inicial: docente evaluador (OBLIGATORIO) + cuentas configurables por .env. */
class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        Usuario::updateOrCreate(['email' => config('peaje.docente_email')], [
            'nombre' => 'Ing. Alvaro Pinto Mamani (Docente)',
            'rol' => Usuario::ROL_ADMIN,
            'activo' => true,
        ]);

        if ($admin = config('peaje.admin_email')) {
            Usuario::updateOrCreate(['email' => mb_strtolower(trim($admin))], [
                'nombre' => 'Administrador del sistema',
                'rol' => Usuario::ROL_ADMIN,
                'activo' => true,
            ]);
        }

        if ($op = config('peaje.operador_email')) {
            Usuario::updateOrCreate(['email' => mb_strtolower(trim($op))], [
                'nombre' => 'Operador de caseta',
                'rol' => Usuario::ROL_OPERADOR,
                'activo' => true,
            ]);
        }
    }
}
