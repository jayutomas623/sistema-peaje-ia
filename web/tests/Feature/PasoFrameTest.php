<?php

namespace Tests\Feature;

use App\Models\Alerta;
use App\Models\EventoHardware;
use App\Models\Paso;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\ConDatosPeaje;
use Tests\TestCase;

class PasoFrameTest extends TestCase
{
    use ConDatosPeaje, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->sembrarBase();
        $this->comoApi();
    }

    private function enviar(string $modo = 'manual')
    {
        return $this->post('/api/v1/pasos/frame', ['imagen' => $this->foto(), 'caseta_id' => $this->caseta()->id, 'modo' => $modo],
            ['Accept' => 'application/json']);
    }

    public function test_vehiculo_registrado_con_saldo_es_autorizado_y_descuenta(): void
    {
        $v = Vehiculo::create(['placa' => '2481-klm', 'propietario' => 'Test', 'categoria' => 'camion', 'ejes' => 3, 'saldo' => 100]);
        $this->fakeIa($this->iaRespuesta('2481KLM', 'camion', 3, 2));

        $r = $this->enviar()->assertCreated()->assertJsonPath('paso.estado', 'autorizado')
            ->assertJsonPath('paso.ejes', 3)->assertJsonPath('paso.pasajeros', 2);
        $this->assertEquals(25.0, $r->json('paso.monto'));              // camión 3 ejes = Bs 25
        $this->assertEquals(75.0, (float) $v->fresh()->saldo);
        $this->assertSame('ok', EventoHardware::latest('id')->first()->tipo_senal);
        Storage::disk('public')->assertExists(Paso::first()->evidencia_path);
        Http::assertSent(fn ($req) => $req->hasHeader('X-API-Token', 'tok') && str_ends_with($req->url(), '/inferir'));
    }

    public function test_placa_no_registrada_crea_alerta_de_evasion(): void
    {
        $this->fakeIa($this->iaRespuesta('1917YBP'));
        $this->enviar()->assertCreated()->assertJsonPath('paso.estado', 'no_registrada');
        $this->assertDatabaseHas('alertas', ['tipo' => 'no_registrada', 'placa' => '1917YBP', 'atendida' => false]);
        $this->assertSame('alerta', EventoHardware::latest('id')->first()->tipo_senal);
    }

    public function test_sin_saldo_no_descuenta_y_alerta(): void
    {
        $v = Vehiculo::create(['placa' => '1703XFY', 'propietario' => 'Test', 'categoria' => 'auto', 'ejes' => 2, 'saldo' => 2]);
        $this->fakeIa($this->iaRespuesta('1703XFY'));
        $this->enviar()->assertCreated()->assertJsonPath('paso.estado', 'sin_saldo');
        $this->assertEquals(2.0, (float) $v->fresh()->saldo);
        $this->assertDatabaseHas('alertas', ['tipo' => 'sin_saldo', 'placa' => '1703XFY']);
    }

    public function test_placa_ilegible_en_modo_manual_registra_y_en_modo_auto_se_ignora(): void
    {
        $this->fakeIa($this->iaRespuesta(null));
        $this->enviar('auto')->assertOk()->assertJsonPath('registrado', false)->assertJsonPath('motivo', 'placa_no_legible_aun');
        $this->assertSame(0, Paso::count());

        $this->enviar('manual')->assertCreated()->assertJsonPath('paso.estado', 'placa_ilegible');
        $this->assertSame(1, Alerta::where('tipo', 'placa_ilegible')->count());
    }

    public function test_frames_consecutivos_del_mismo_vehiculo_no_cobran_dos_veces(): void
    {
        Vehiculo::create(['placa' => '1151DKL', 'propietario' => 'Test', 'categoria' => 'auto', 'ejes' => 2, 'saldo' => 50]);
        $this->fakeIa($this->iaRespuesta('1151DKL'));
        $this->enviar('auto')->assertCreated();
        $this->enviar('auto')->assertOk()->assertJsonPath('duplicado', true);
        $this->assertSame(1, Paso::count());
        $this->assertEquals(44.0, (float) Vehiculo::first()->saldo);
    }

    public function test_correccion_ocr_por_similitud_encuentra_vehiculo(): void
    {
        Vehiculo::create(['placa' => '1483AXF', 'propietario' => 'Test', 'categoria' => 'auto', 'ejes' => 2, 'saldo' => 50]);
        $this->fakeIa($this->iaRespuesta('1483AKF'));    // K↔X confundidos por el OCR
        $this->enviar()->assertCreated()->assertJsonPath('paso.estado', 'autorizado');
        $this->assertStringContainsString('1483AXF', Paso::first()->nota);
    }

    public function test_vista_frontal_usa_ejes_registrados_del_vehiculo(): void
    {
        Vehiculo::create(['placa' => '2950KPL', 'propietario' => 'Test', 'categoria' => 'camion', 'ejes' => 5, 'saldo' => 500]);
        $this->fakeIa($this->iaRespuesta('2950KPL', 'camion', null));
        $r = $this->enviar()->assertCreated();
        $this->assertSame(5, $r->json('paso.ejes'));
        $this->assertEquals(45.0, $r->json('paso.monto'));           // camión 5 ejes = Bs 45
    }

    public function test_servidor_de_inferencia_caido_genera_alerta_en_modo_manual(): void
    {
        Http::fake(['*/inferir' => Http::response('boom', 500)]);
        $this->enviar()->assertCreated()->assertJsonPath('paso.estado', 'error_inferencia');
        $this->assertDatabaseHas('alertas', ['tipo' => 'error_inferencia']);
    }
}
