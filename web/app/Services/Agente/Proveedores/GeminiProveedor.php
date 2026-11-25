<?php

namespace App\Services\Agente\Proveedores;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Google Gemini API (generateContent) con function calling. */
class GeminiProveedor implements ProveedorLlm
{
    public function __construct(private string $apiKey, private string $modelo, private string $baseUrl, private int $timeout = 25)
    {
    }

    public function nombre(): string
    {
        return 'gemini:'.$this->modelo;
    }

    public function responder(string $sistema, array $mensajes, array $herramientas, callable $ejecutor): string
    {
        $contents = array_map(fn ($m) => ['role' => $m['rol'] === 'agente' ? 'model' : 'user',
            'parts' => [['text' => $m['texto']]]], $mensajes);
        $tools = [['functionDeclarations' => array_map(function ($h) {
            if ($h['parameters']['properties'] instanceof \stdClass) {
                unset($h['parameters']);   // Gemini no acepta objetos sin propiedades
            }

            return $h;
        }, $herramientas)]];

        for ($i = 0; $i < 5; $i++) {
            $r = Http::timeout($this->timeout)->withHeaders(['x-goog-api-key' => $this->apiKey])
                ->post(rtrim($this->baseUrl, '/')."/models/{$this->modelo}:generateContent", [
                    'systemInstruction' => ['parts' => [['text' => $sistema]]],
                    'contents' => $contents,
                    'tools' => $tools,
                    'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 1024],
                ]);
            if (! $r->successful()) {
                throw new RuntimeException('Gemini HTTP '.$r->status().': '.mb_substr($r->body(), 0, 300));
            }
            $contenido = $r->json('candidates.0.content');
            $partes = $contenido['parts'] ?? [];
            $llamadas = array_values(array_filter($partes, fn ($p) => isset($p['functionCall'])));
            if (! $llamadas) {
                $texto = trim(implode('', array_map(fn ($p) => $p['text'] ?? '', $partes)));
                if ($texto === '') {
                    throw new RuntimeException('Gemini devolvió una respuesta vacía');
                }

                return $texto;
            }
            $contents[] = $contenido;   // se reenvía tal cual (incluye thoughtSignature si existe)
            $respuestas = [];
            foreach ($llamadas as $p) {
                $nombre = $p['functionCall']['name'];
                $args = (array) ($p['functionCall']['args'] ?? []);
                $respuestas[] = ['functionResponse' => ['name' => $nombre, 'response' => ['resultado' => $ejecutor($nombre, $args)]]];
            }
            $contents[] = ['role' => 'user', 'parts' => $respuestas];
        }
        throw new RuntimeException('Gemini excedió el máximo de llamadas a herramientas');
    }
}
