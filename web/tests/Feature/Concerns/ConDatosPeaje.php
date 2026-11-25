<?php

namespace Tests\Feature\Concerns;

use App\Models\Caseta;
use App\Models\Usuario;
use Database\Seeders\CasetaSeeder;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\TarifaSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

trait ConDatosPeaje
{
    protected function sembrarBase(): void
    {
        config(['peaje.inference.url' => 'https://pod-test-8000.proxy.runpod.net', 'peaje.inference.token' => 'tok',
            'peaje.hardware_token' => 'hw-secreto', 'peaje.agente.proveedor' => 'ninguno',
            'broadcasting.default' => 'null', 'peaje.google_client_ids' => ['web-client.apps.googleusercontent.com']]);
        $this->seed([UsuarioSeeder::class, CasetaSeeder::class, TarifaSeeder::class, ConfiguracionSeeder::class]);
    }

    protected function docente(): Usuario
    {
        return Usuario::where('email', 'doc.alvaro.pinto.ma@unifranz.edu.bo')->firstOrFail();
    }

    protected function operador(): Usuario
    {
        return Usuario::create(['nombre' => 'Op', 'email' => 'operador@test.bo', 'rol' => 'operador', 'activo' => true]);
    }

    protected function comoApi(?Usuario $u = null): Usuario
    {
        $u ??= $this->docente();
        Sanctum::actingAs($u, [$u->rol]);

        return $u;
    }

    protected function caseta(): Caseta
    {
        return Caseta::where('codigo', 'AUT-01')->firstOrFail();
    }

    /** Respuesta simulada de INFERENCE_URL/inferir con el contrato JSON exacto. */
    protected function iaRespuesta(?string $placa, string $categoria = 'auto', ?int $ejes = null, int $pasajeros = 1, float $conf = 0.91): array
    {
        $px = base64_encode(str_repeat('x', 300));

        return [
            'placa' => ['texto' => $placa, 'texto_crudo' => $placa, 'formato_bolivia' => (bool) $placa, 'confianza' => $placa ? $conf : 0.0,
                'bbox' => $placa ? [290, 370, 470, 460] : null],
            'vehiculo' => ['categoria' => $categoria, 'confianza' => 0.88, 'area_mascara' => 0.31, 'bbox' => [40, 120, 800, 560]],
            'ejes' => $ejes,
            'vista_vehiculo' => $ejes ? 'lateral' : 'frontal/posterior',
            'keypoints' => $ejes ? [[170, 500, 0.95], [270, 500, 0.93], [680, 500, 0.96]] : [],
            'pasajeros' => ['conteo' => $pasajeros, 'confianza_promedio' => 0.84],
            'imagen_anotada_base64' => 'data:image/jpeg;base64,'.$px,
            'latencia_ms' => 142.5,
        ];
    }

    protected function fakeIa(array $respuesta): void
    {
        Http::fake([
            '*/inferir' => Http::response($respuesta),
            '*/inferir-placa' => Http::response([
                'placa' => $respuesta['placa'], 'placas' => [$respuesta['placa']],
                'legible' => (bool) ($respuesta['placa']['texto'] ?? null),
                'imagen_anotada_base64' => $respuesta['imagen_anotada_base64'], 'latencia_ms' => 30,
            ]),
            '*/health' => Http::response(['estado' => 'ok', 'gpu' => ['cuda_disponible' => true, 'nombre' => 'RTX A4000']]),
        ]);
    }

    protected function foto(): UploadedFile
    {
        return UploadedFile::fake()->image('frame.jpg', 640, 480);
    }
}
