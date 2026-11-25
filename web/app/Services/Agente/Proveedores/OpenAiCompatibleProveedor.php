<?php

namespace App\Services\Agente\Proveedores;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** API compatible con OpenAI (/chat/completions + tools): OpenAI, Groq u Ollama en RunPod. */
class OpenAiCompatibleProveedor implements ProveedorLlm
{
    public function __construct(private string $etiqueta, private ?string $apiKey, private string $modelo,
        private string $baseUrl, private int $timeout = 25)
    {
    }

    public function nombre(): string
    {
        return $this->etiqueta.':'.$this->modelo;
    }

    public function responder(string $sistema, array $mensajes, array $herramientas, callable $ejecutor): string
    {
        $msgs = [['role' => 'system', 'content' => $sistema]];
        foreach ($mensajes as $m) {
            $msgs[] = ['role' => $m['rol'] === 'agente' ? 'assistant' : 'user', 'content' => $m['texto']];
        }
        $tools = array_map(fn ($h) => ['type' => 'function', 'function' => $h], $herramientas);

        for ($i = 0; $i < 5; $i++) {
            $r = Http::timeout($this->timeout)->withToken((string) $this->apiKey)
                ->post(rtrim($this->baseUrl, '/').'/chat/completions', [
                    'model' => $this->modelo, 'messages' => $msgs, 'tools' => $tools,
                    'tool_choice' => 'auto', 'temperature' => 0.1,
                ]);
            if (! $r->successful()) {
                throw new RuntimeException($this->etiqueta.' HTTP '.$r->status().': '.mb_substr($r->body(), 0, 300));
            }
            $msg = $r->json('choices.0.message');
            $llamadas = $msg['tool_calls'] ?? [];
            if (! $llamadas) {
                $texto = trim((string) ($msg['content'] ?? ''));
                if ($texto === '') {
                    throw new RuntimeException($this->etiqueta.' devolvió una respuesta vacía');
                }

                return $texto;
            }
            $msgs[] = ['role' => 'assistant', 'content' => $msg['content'] ?? null, 'tool_calls' => $llamadas];
            foreach ($llamadas as $c) {
                $args = json_decode($c['function']['arguments'] ?? '{}', true) ?: [];
                $msgs[] = ['role' => 'tool', 'tool_call_id' => $c['id'],
                    'content' => json_encode($ejecutor($c['function']['name'], $args), JSON_UNESCAPED_UNICODE)];
            }
        }
        throw new RuntimeException($this->etiqueta.' excedió el máximo de llamadas a herramientas');
    }
}
