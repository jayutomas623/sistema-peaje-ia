<?php

namespace App\Services;

use App\Models\Configuracion;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente HTTP del servidor de inferencia (RunPod o Colab). La URL y el token se leen de la tabla
 * `configuraciones` (editable desde Estado del sistema) con respaldo en .env.
 */
class InferenciaClient
{
    public function url(): string
    {
        return rtrim((string) Configuracion::obtener('INFERENCE_URL', config('peaje.inference.url')), '/');
    }

    public function token(): string
    {
        return (string) Configuracion::obtener('INFERENCE_TOKEN', config('peaje.inference.token'));
    }

    public function configurado(): bool
    {
        return $this->url() !== '';
    }

    /** @return array<string, mixed> */
    public function inferir(string $bytes): array
    {
        return $this->post('/inferir', $bytes);
    }

    /** @return array<string, mixed> */
    public function inferirPlaca(string $bytes): array
    {
        return $this->post('/inferir-placa', $bytes);
    }

    /** @return array{ok: bool, latencia_ms: float, datos: ?array, error: ?string, url: string} */
    public function health(): array
    {
        $url = $this->url();
        if ($url === '') {
            return ['ok' => false, 'latencia_ms' => 0, 'datos' => null, 'error' => 'INFERENCE_URL no configurada', 'url' => ''];
        }
        $t0 = microtime(true);
        try {
            $r = Http::timeout(15)->acceptJson()->get($url.'/health');
            $ms = round((microtime(true) - $t0) * 1000, 1);

            return ['ok' => $r->successful(), 'latencia_ms' => $ms, 'datos' => $r->json(),
                'error' => $r->successful() ? null : 'HTTP '.$r->status(), 'url' => $url];
        } catch (\Throwable $e) {
            return ['ok' => false, 'latencia_ms' => round((microtime(true) - $t0) * 1000, 1), 'datos' => null,
                'error' => $e->getMessage(), 'url' => $url];
        }
    }

    /** @return array<string, mixed> */
    private function post(string $ruta, string $bytes): array
    {
        if (! $this->configurado()) {
            throw new RuntimeException('INFERENCE_URL no está configurada (Panel > Estado del sistema).');
        }
        try {
            $r = Http::timeout((int) config('peaje.inference.timeout', 30))
                ->withHeaders(['X-API-Token' => $this->token()])
                ->attach('imagen', $bytes, 'frame.jpg', ['Content-Type' => 'image/jpeg'])
                ->post($this->url().$ruta);
        } catch (ConnectionException $e) {
            throw new RuntimeException('No se pudo conectar con el servidor de inferencia: '.$e->getMessage());
        }
        if ($r->status() === 401) {
            throw new RuntimeException('El servidor de inferencia rechazó el token (INFERENCE_TOKEN).');
        }
        if (! $r->successful()) {
            throw new RuntimeException('Servidor de inferencia respondió HTTP '.$r->status().': '.mb_substr($r->body(), 0, 200));
        }

        return $r->json() ?? [];
    }
}
