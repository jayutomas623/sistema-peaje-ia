<?php

/*
| Tiempo real del panel. Por defecto Pusher (Channels, plan gratuito): funciona en hosting compartido
| y VPS sin abrir puertos de WebSocket. Alternativa auto-hospedada: Laravel Reverb (BROADCAST_CONNECTION=reverb).
|
| Si el driver elegido no tiene su clave (durante `docker build`, o PUSHER_APP_KEY aún sin cargar en Render) se cae
| de forma segura a 'log': ningún comando Artisan ni request falla y el panel usa sondeo HTTP en lugar de WebSocket.
*/
$broadcaster = env('BROADCAST_CONNECTION', 'pusher');
if (($broadcaster === 'pusher' && blank(env('PUSHER_APP_KEY')))
    || ($broadcaster === 'reverb' && blank(env('REVERB_APP_KEY')))) {
    $broadcaster = 'log';
}

return [
    'default' => $broadcaster,

    'connections' => [
        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER', 'us2'),
                'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'us2').'.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
            'client_options' => ['timeout' => 5],
        ],

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
            'client_options' => ['timeout' => 5],
        ],

        'log' => ['driver' => 'log'],
        'null' => ['driver' => 'null'],
    ],
];
