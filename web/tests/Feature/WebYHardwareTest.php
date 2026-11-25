<?php

namespace Tests\Feature;

use App\Models\EventoHardware;
use App\Models\Usuario;
use Database\Seeders\PasoSeeder;
use Database\Seeders\VehiculoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\ConDatosPeaje;
use Tests\TestCase;

class WebYHardwareTest extends TestCase
{
    use ConDatosPeaje, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->sembrarBase();
    }

    public function test_seeder_precarga_docente_como_administrador_activo(): void
    {
        $this->assertDatabaseHas('usuarios', ['email' => 'doc.alvaro.pinto.ma@unifranz.edu.bo', 'rol' => 'administrador', 'activo' => true]);
    }

    public function test_seeder_carga_22_vehiculos_y_pasos_con_evidencias(): void
    {
        $this->seed([VehiculoSeeder::class, PasoSeeder::class]);
        $this->assertDatabaseCount('vehiculos', 22);
        $this->assertGreaterThanOrEqual(20, \App\Models\Paso::count());
        $this->assertGreaterThan(0, \App\Models\Paso::where('estado', 'no_registrada')->count());
        $this->assertGreaterThan(0, \App\Models\Paso::where('estado', 'placa_ilegible')->count());
    }

    public function test_invitado_es_enviado_al_login_de_google(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Iniciar sesión con Google')->assertDontSee('password');
    }

    public function test_pagina_de_acceso_denegado(): void
    {
        $this->withSession(['email' => 'intruso@gmail.com'])->get('/acceso-denegado')
            ->assertOk()->assertSee('Acceso denegado')->assertSee('intruso@gmail.com');
    }

    public function test_panel_y_paginas_principales_cargan_para_administrador(): void
    {
        $this->seed([VehiculoSeeder::class, PasoSeeder::class]);
        $this->actingAs($this->docente());
        foreach (['/', '/pasos', '/alertas', '/vehiculos', '/tarifas', '/casetas', '/usuarios', '/chat', '/estado'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/')->assertSee('burbuja-chat-btn', false)->assertSee('Pasos en vivo');
    }

    public function test_operador_no_accede_a_crud_de_administrador(): void
    {
        $this->actingAs($this->operador());
        $this->get('/vehiculos')->assertOk();
        $this->get('/vehiculos/create')->assertForbidden();
        $this->get('/usuarios')->assertForbidden();
        $this->post('/estado', ['inference_url' => 'https://x.proxy.runpod.net', 'prioridad_categoria' => 'ia'])->assertForbidden();
    }

    public function test_admin_actualiza_inference_url_sin_redeploy(): void
    {
        $this->actingAs($this->docente());
        $this->post('/estado', ['inference_url' => 'https://nuevo-8000.proxy.runpod.net/', 'inference_token' => 'secreto',
            'prioridad_categoria' => 'registro'])->assertRedirect();
        $ia = app(\App\Services\InferenciaClient::class);
        $this->assertSame('https://nuevo-8000.proxy.runpod.net', $ia->url());
        $this->assertSame('secreto', $ia->token());
        $this->assertDatabaseMissing('configuraciones', ['valor' => 'secreto']);   // cifrado
    }

    public function test_crud_vehiculo_normaliza_placa(): void
    {
        $this->actingAs($this->docente());
        $this->post('/vehiculos', ['placa' => '3456-bcd', 'propietario' => 'Ana', 'categoria' => 'auto', 'ejes' => 2, 'saldo' => 10, 'activo' => 1])
            ->assertRedirect();
        $this->assertDatabaseHas('vehiculos', ['placa' => '3456BCD']);
        $this->post('/vehiculos', ['placa' => 'ABC123', 'propietario' => 'X', 'categoria' => 'auto', 'ejes' => 2, 'saldo' => 0])
            ->assertSessionHasErrors('placa');
    }

    public function test_usuario_no_puede_quitarse_su_propio_rol_admin(): void
    {
        $doc = $this->docente();
        $this->actingAs($doc)->put("/usuarios/{$doc->id}", ['nombre' => 'x', 'email' => $doc->email, 'rol' => 'operador', 'activo' => 1])
            ->assertSessionHas('error');
        $this->assertSame(Usuario::ROL_ADMIN, $doc->fresh()->rol);
    }

    public function test_endpoint_hardware_exige_token_y_devuelve_senal(): void
    {
        $this->getJson('/api/v1/hardware/ultimo-evento')->assertStatus(401);
        EventoHardware::emitir('alerta', ['placa' => '1917YBP', 'monto' => 6]);
        $this->getJson('/api/v1/hardware/ultimo-evento?rssi=-61&fw=1.0', ['X-Hardware-Token' => 'hw-secreto'])
            ->assertOk()->assertJsonPath('tipo_senal', 'alerta')->assertJsonPath('placa', '1917YBP')
            ->assertJsonStructure(['id', 'tipo_senal', 'placa', 'monto', 'timestamp']);
    }

    public function test_evento_antiguo_vuelve_a_idle(): void
    {
        $e = EventoHardware::emitir('ok', ['placa' => '1151DKL']);
        $e->forceFill(['created_at' => now()->subMinute()])->save();
        $this->getJson('/api/v1/hardware/ultimo-evento', ['X-Hardware-Token' => 'hw-secreto'])->assertJsonPath('tipo_senal', 'idle');
    }

    public function test_comando_exporta_bd_sin_secretos(): void
    {
        $this->seed([VehiculoSeeder::class, PasoSeeder::class]);
        $dir = storage_path('framework/testing/export');
        File::deleteDirectory($dir);
        $this->artisan('peaje:exportar-bd', ['--salida' => $dir])->assertSuccessful();
        $json = File::glob($dir.'/*.json');
        $this->assertCount(1, $json);
        $datos = json_decode(File::get($json[0]), true);
        $this->assertCount(22, $datos['tablas']['vehiculos']);
        $this->assertStringNotContainsString('tok', json_encode($datos['tablas']['configuraciones']));
        $this->assertNotEmpty(File::glob($dir.'/*.sql'));
        File::deleteDirectory($dir);
    }
}
