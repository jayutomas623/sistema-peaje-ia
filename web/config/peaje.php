<?php

return [
    // Servidor de inferencia (RunPod / Colab). Se puede sobrescribir desde el panel (tabla configuraciones).
    'inference' => [
        'url' => env('INFERENCE_URL', ''),
        'token' => env('INFERENCE_TOKEN', ''),
        'timeout' => (int) env('INFERENCE_TIMEOUT', 30),
    ],

    // Token del ESP32 (header X-Hardware-Token)
    'hardware_token' => env('HARDWARE_TOKEN', ''),
    // Segundos tras los cuales el último evento deja de ser "vigente" y el ESP32 vuelve a idle
    'hardware_vigencia_segundos' => (int) env('HARDWARE_VIGENCIA_SEGUNDOS', 12),

    // Host público de Reverb para el navegador (si BROADCAST_CONNECTION=reverb)
    'reverb_publico' => [
        'host' => env('REVERB_PUBLIC_HOST', env('REVERB_HOST')),
        'port' => (int) env('REVERB_PUBLIC_PORT', 443),
    ],

    // Correos precargados en la lista blanca
    'docente_email' => 'doc.alvaro.pinto.ma@unifranz.edu.bo',
    'admin_email' => env('ADMIN_EMAIL'),
    'operador_email' => env('OPERADOR_EMAIL'),

    // Google: client IDs aceptados como "aud" del ID Token (web + Android usan el Web Client ID)
    'google_client_ids' => array_values(array_filter(array_map('trim', array_merge(
        [env('GOOGLE_CLIENT_ID', '')],
        explode(',', (string) env('GOOGLE_CLIENT_IDS_EXTRA', ''))
    )))),

    // Reglas de negocio
    'dedup_segundos' => (int) env('PEAJE_DEDUP_SEGUNDOS', 60),
    'umbral_placa' => (float) env('PEAJE_UMBRAL_PLACA', 0.45),
    'prioridad_categoria' => env('PEAJE_PRIORIDAD_CATEGORIA', 'ia'),   // ia | registro

    // Agente conversacional
    'agente' => [
        'proveedor' => env('AGENTE_PROVEEDOR', 'gemini'),               // gemini | openai | groq | ollama | ninguno
        'timeout' => (int) env('AGENTE_TIMEOUT', 25),
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'modelo' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        ],
        'openai' => [   // cualquier API compatible con OpenAI: OpenAI, Groq, Ollama (RunPod)
            'api_key' => env('OPENAI_API_KEY'),
            'modelo' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],
        'groq' => [
            'api_key' => env('GROQ_API_KEY'),
            'modelo' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
            'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        ],
        'ollama' => [
            'api_key' => env('OLLAMA_API_KEY', 'ollama'),
            'modelo' => env('OLLAMA_MODEL', 'qwen2.5:14b'),
            'base_url' => env('OLLAMA_BASE_URL', ''),                       // https://<POD_ID>-11434.proxy.runpod.net/v1
        ],
    ],
];
