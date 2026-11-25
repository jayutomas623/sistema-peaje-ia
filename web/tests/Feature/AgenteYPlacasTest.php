<?php

namespace Tests\Feature;

use Database\Seeders\PasoSeeder;
use Database\Seeders\VehiculoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\ConDatosPeaje;
use Tests\TestCase;

/** Los 4 estados exactos del agente y la búsqueda difusa "¿Quisiste decir …?" sobre los datos del seeder. */
class AgenteYPlacasTest extends TestCase
{
    use ConDatosPeaje, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->sembrarBase();
        $this->seed([VehiculoSeeder::class, PasoSeeder::class]);
        $this->comoApi($this->operador());
    }

    public function test_estado_1_registrada_con_paso(): void
    {
        $r = $this->postJson('/api/v1/agente/chat', ['mensaje' => '¿Pasó la placa 1151-dkl?'])->assertOk();
        $r->assertJsonPath('estado_codigo', 1)->assertJsonPath('placa', '1151DKL')->assertJsonPath('fuente', 'determinista');
        $this->assertStringContainsString('REGISTRADA Y CON PASO', $r->json('respuesta'));
        $this->assertStringContainsString('Bs', $r->json('respuesta'));
    }

    public function test_estado_2_registrada_sin_paso(): void
    {
        $this->postJson('/api/v1/placas/consultar', ['placa' => '5592 UTF'])->assertOk()
            ->assertJsonPath('estado_codigo', 2)->assertJsonPath('estado', 'registrada_sin_paso');
    }

    public function test_estado_3_identificada_no_registrada(): void
    {
        $r = $this->postJson('/api/v1/agente/chat', ['mensaje' => 'consulta 1917YBP'])->assertOk()->assertJsonPath('estado_codigo', 3);
        $this->assertStringContainsString('evasión', $r->json('respuesta'));
    }

    public function test_estado_4_no_existe_con_sugerencia_por_error_ocr(): void
    {
        // 3↔8 confundidos: 1958LNH no existe, 1985LNH sí (y 1153DKL -> 1151DKL)
        $r = $this->postJson('/api/v1/agente/chat', ['mensaje' => '¿pasó la 1153DKL?'])->assertOk()->assertJsonPath('estado_codigo', 4);
        $this->assertContains('1151DKL', $r->json('sugerencias'));
        $this->assertStringContainsString('¿Quisiste decir 1151DKL?', $r->json('respuesta'));
    }

    public function test_confusion_3_8_sugiere_placa_correcta(): void
    {
        $r = $this->postJson('/api/v1/placas/consultar', ['placa' => '1935CXX'])->assertOk();   // 1985CXX real
        $this->assertSame('1985CXX', $r->json('sugerencias.0.placa'));
    }

    public function test_historial_y_resumen_de_caseta(): void
    {
        $this->getJson('/api/v1/placas/1985LNH/historial')->assertOk()->assertJsonCount(2, 'pasos');
        $this->getJson('/api/v1/casetas/'.$this->caseta()->id.'/resumen')->assertOk()
            ->assertJsonStructure(['pasos', 'recaudacion_bs', 'alertas_activas', 'ultimos_pasos']);
    }

    public function test_imagen_sin_placa_legible_pide_otra_foto(): void
    {
        $this->fakeIa($this->iaRespuesta(null));
        $r = $this->post('/api/v1/agente/chat', ['imagen' => $this->foto()], ['Accept' => 'application/json'])->assertOk();
        $r->assertJsonPath('estado', 'imagen_sin_placa');
        $this->assertStringContainsString('otra foto', $r->json('respuesta'));
    }

    public function test_imagen_con_placa_se_consulta_en_bd(): void
    {
        $this->fakeIa($this->iaRespuesta('1078LPS'));
        $this->post('/api/v1/placas/consultar-imagen', ['imagen' => $this->foto()], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('estado_codigo', 1)->assertJsonPath('placa', '1078LPS');
    }

    public function test_agente_usa_gemini_con_function_calling_y_cae_a_determinista_si_falla(): void
    {
        config(['peaje.agente.proveedor' => 'gemini', 'peaje.agente.gemini.api_key' => 'k']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['candidates' => [['content' => ['role' => 'model', 'parts' => [['functionCall' => ['name' => 'consultar_placa', 'args' => ['placa' => '1151DKL']]]]]]]])
            ->push(['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => 'La placa 1151DKL está registrada y con paso.']]]]]])
            ->push('error', 500)]);
        $this->postJson('/api/v1/agente/chat', ['mensaje' => '¿pasó la 1151DKL?'])->assertOk()
            ->assertJsonPath('fuente', 'llm:gemini:gemini-2.5-flash')->assertJsonPath('estado_codigo', 1);
        $this->postJson('/api/v1/agente/chat', ['mensaje' => '¿pasó la 1151DKL?'])->assertOk()
            ->assertJsonPath('fuente', 'determinista')->assertJsonPath('estado_codigo', 1);
    }
}
