<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Verifica un Google ID Token (app Flutter con google_sign_in) usando el endpoint oficial tokeninfo.
 * Valida: firma (Google), emisor, audiencia (Web Client ID), expiración y email verificado.
 */
class GoogleTokenVerifier
{
    public const TOKENINFO = 'https://oauth2.googleapis.com/tokeninfo';

    /** @return array{email: string, nombre: string, google_id: string, avatar: ?string} */
    public function verificar(string $idToken): array
    {
        $r = Http::timeout(10)->get(self::TOKENINFO, ['id_token' => $idToken]);
        if (! $r->successful()) {
            throw new RuntimeException('ID Token de Google inválido o expirado');
        }
        $c = $r->json();
        if (! in_array($c['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)) {
            throw new RuntimeException('Emisor del token inválido');
        }
        $permitidos = config('peaje.google_client_ids', []);
        if ($permitidos && ! in_array($c['aud'] ?? '', $permitidos, true)) {
            throw new RuntimeException('El token no fue emitido para esta aplicación (aud)');
        }
        if ((int) ($c['exp'] ?? 0) < time()) {
            throw new RuntimeException('Token expirado');
        }
        if (! filter_var($c['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('El correo de Google no está verificado');
        }

        return [
            'email' => mb_strtolower($c['email']),
            'nombre' => $c['name'] ?? $c['email'],
            'google_id' => (string) ($c['sub'] ?? ''),
            'avatar' => $c['picture'] ?? null,
        ];
    }
}
