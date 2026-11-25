<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\ConDatosPeaje;
use Tests\TestCase;

class AuthGoogleApiTest extends TestCase
{
    use ConDatosPeaje, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarBase();
    }

    private function fakeTokeninfo(string $email, string $aud = 'web-client.apps.googleusercontent.com'): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response([
            'iss' => 'https://accounts.google.com', 'aud' => $aud, 'sub' => '1234567890', 'email' => $email,
            'email_verified' => 'true', 'name' => 'Persona', 'exp' => (string) (time() + 3600),
        ])]);
    }

    public function test_docente_en_lista_blanca_obtiene_token_y_rol_administrador(): void
    {
        $this->fakeTokeninfo('doc.alvaro.pinto.ma@unifranz.edu.bo');
        $r = $this->postJson('/api/v1/auth/google', ['id_token' => str_repeat('a', 40)]);
        $r->assertOk()->assertJsonPath('rol', 'administrador')->assertJsonStructure(['token', 'usuario' => ['email', 'rol']]);

        $this->withToken($r->json('token'))->getJson('/api/v1/auth/yo')->assertOk()
            ->assertJsonPath('usuario.email', 'doc.alvaro.pinto.ma@unifranz.edu.bo');
    }

    public function test_cuenta_no_autorizada_recibe_403_acceso_denegado(): void
    {
        $this->fakeTokeninfo('intruso@gmail.com');
        $this->postJson('/api/v1/auth/google', ['id_token' => str_repeat('a', 40)])
            ->assertStatus(403)->assertJsonPath('message', 'Acceso denegado: cuenta no autorizada');
    }

    public function test_token_emitido_para_otra_aplicacion_es_rechazado(): void
    {
        $this->fakeTokeninfo('doc.alvaro.pinto.ma@unifranz.edu.bo', 'otra-app.apps.googleusercontent.com');
        $this->postJson('/api/v1/auth/google', ['id_token' => str_repeat('a', 40)])->assertStatus(401);
    }

    public function test_usuario_desactivado_pierde_acceso_api(): void
    {
        $u = $this->operador();
        $token = $u->createToken('t')->plainTextToken;
        $u->update(['activo' => false]);
        $this->withToken($token)->getJson('/api/v1/casetas')->assertStatus(403);
    }

    public function test_api_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/v1/casetas')->assertStatus(401);
    }
}
