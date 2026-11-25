<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas del dominio: casetas, tarifas, vehiculos, pasos, alertas,
 * configuraciones (INFERENCE_URL editable sin redeploy) y eventos_hardware (señales ESP32).
 */
return new class extends Migration
{
    public const CATEGORIAS = ['moto', 'auto', 'minibus', 'bus', 'camion'];

    public function up(): void
    {
        Schema::create('casetas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre');
            $table->string('ubicacion')->nullable();
            $table->string('carretera')->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('tarifas', function (Blueprint $table) {
            $table->id();
            $table->enum('categoria', self::CATEGORIAS);
            $table->unsignedTinyInteger('ejes');          // 2..6 (6 = 6 o más ejes)
            $table->decimal('monto', 10, 2);              // bolivianos (Bs)
            $table->string('descripcion')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->unique(['categoria', 'ejes']);
        });

        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('placa', 12)->unique();        // normalizada: 1234ABC
            $table->string('propietario');
            $table->string('ci_nit', 30)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->enum('categoria', self::CATEGORIAS)->default('auto');
            $table->unsignedTinyInteger('ejes')->default(2);
            $table->string('marca', 60)->nullable();
            $table->string('modelo', 60)->nullable();
            $table->string('color', 40)->nullable();
            $table->unsignedSmallInteger('anio')->nullable();
            $table->decimal('saldo', 10, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->string('foto_referencia')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        Schema::create('pasos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->foreignId('caseta_id')->constrained('casetas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('placa_detectada', 12)->nullable()->index();
            $table->string('placa_cruda', 20)->nullable();
            $table->decimal('placa_confianza', 5, 3)->nullable();
            $table->boolean('formato_valido')->default(false);
            $table->string('categoria_detectada', 20)->nullable();
            $table->decimal('categoria_confianza', 5, 3)->nullable();
            $table->string('categoria_cobrada', 20)->nullable();
            $table->unsignedTinyInteger('ejes_detectados')->nullable();
            $table->unsignedTinyInteger('ejes_cobrados')->nullable();
            $table->string('vista_vehiculo', 30)->nullable();
            $table->integer('pasajeros')->nullable();     // M4 (bono): conteo de personas dentro del vehículo
            $table->decimal('pasajeros_confianza', 5, 3)->nullable();
            $table->foreignId('tarifa_id')->nullable()->constrained('tarifas')->nullOnDelete();
            $table->decimal('monto', 10, 2)->default(0);
            $table->decimal('saldo_anterior', 10, 2)->nullable();
            $table->decimal('saldo_posterior', 10, 2)->nullable();
            $table->enum('estado', ['autorizado', 'sin_saldo', 'no_registrada', 'placa_ilegible', 'error_inferencia'])
                ->index();
            $table->string('evidencia_path')->nullable();
            $table->decimal('latencia_ms', 10, 1)->nullable();
            $table->string('origen', 20)->default('api');  // movil | web | api | seeder
            $table->string('nota')->nullable();
            $table->json('respuesta_ia')->nullable();     // JSON de inferencia sin la imagen base64
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paso_id')->nullable()->constrained('pasos')->cascadeOnDelete();
            $table->foreignId('caseta_id')->nullable()->constrained('casetas')->nullOnDelete();
            $table->enum('tipo', ['no_registrada', 'sin_saldo', 'placa_ilegible', 'error_inferencia']);
            $table->string('placa', 12)->nullable()->index();
            $table->string('mensaje');
            $table->enum('severidad', ['alta', 'media', 'baja'])->default('media');
            $table->boolean('atendida')->default(false)->index();
            $table->foreignId('atendida_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('atendida_at')->nullable();
            $table->timestamps();
        });

        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 60)->unique();
            $table->text('valor')->nullable();
            $table->boolean('es_secreto')->default(false);   // se guarda cifrado (APP_KEY)
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('eventos_hardware', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo_senal', ['idle', 'inferencia', 'ok', 'alerta']);
            $table->string('placa', 12)->nullable();
            $table->decimal('monto', 10, 2)->nullable();
            $table->foreignId('paso_id')->nullable()->constrained('pasos')->nullOnDelete();
            $table->foreignId('caseta_id')->nullable()->constrained('casetas')->nullOnDelete();
            $table->string('mensaje')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_hardware');
        Schema::dropIfExists('configuraciones');
        Schema::dropIfExists('alertas');
        Schema::dropIfExists('pasos');
        Schema::dropIfExists('vehiculos');
        Schema::dropIfExists('tarifas');
        Schema::dropIfExists('casetas');
    }
};
