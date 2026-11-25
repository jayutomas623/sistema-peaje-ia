<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lista blanca de cuentas de Google autorizadas. NO hay contraseñas:
 * la autenticación es exclusivamente con Google (web y móvil).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('email')->unique();
            $table->string('google_id')->nullable()->unique();
            $table->string('avatar', 500)->nullable();
            $table->enum('rol', ['administrador', 'operador'])->default('operador');
            $table->boolean('activo')->default(true);
            $table->timestamp('ultimo_acceso_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('usuarios');
    }
};
