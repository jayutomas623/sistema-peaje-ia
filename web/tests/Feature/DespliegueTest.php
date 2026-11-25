<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Models\Vehiculo;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Comportamiento del arranque en Render/Docker (docker-entrypoint.sh) y detrás del proxy HTTPS. */
class DespliegueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['broadcasting.default' => 'null']);
    }

    public function test_sembrar_si_vacio_siembra_solo_la_primera_vez_y_repone_fotos(): void
    {
        $this->assertSame(0, Usuario::count());

        $this->artisan('peaje:sembrar-si-vacio')->expectsOutputToContain('ejecutando seeders')->assertSuccessful();
        $this->assertDatabaseHas('usuarios', ['email' => 'doc.alvaro.pinto.ma@unifranz.edu.bo', 'rol' => 'administrador', 'activo' => true]);
        $usuarios = Usuario::count();
        $vehiculos = Vehiculo::count();
        $this->assertSame(22, $vehiculos);

        // Redeploy en Render: la BD persiste pero el disco efímero perdió las fotos
        Storage::disk('public')->deleteDirectory('evidencias/seed');
        $this->artisan('peaje:sembrar-si-vacio')->expectsOutputToContain('se omiten los seeders')->assertSuccessful();

        $this->assertSame($usuarios, Usuario::count());
        $this->assertSame($vehiculos, Vehiculo::count());
        Storage::disk('public')->assertExists('evidencias/seed/'.basename(glob(database_path('seeders/evidencias/*.jpg'))[0]));
    }

    public function test_detras_del_proxy_https_las_urls_generadas_son_https(): void
    {
        $html = $this->get('/login', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#href="https://[^"/]+/auth/google/redirect"#', $html);
    }

    public function test_en_produccion_se_fuerza_https(): void
    {
        $this->app['env'] = 'production';
        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('https://', route('google.callback'));
        $this->assertStringStartsWith('https://', url('/storage/evidencias/seed/x.jpg'));
    }
}
