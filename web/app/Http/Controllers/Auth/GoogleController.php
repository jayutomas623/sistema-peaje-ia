<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Login EXCLUSIVO con Google OAuth 2.0 (Laravel Socialite) + lista blanca `usuarios`.
 * No existe login con usuario y contraseña.
 */
class GoogleController extends Controller
{
    public function login(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('panel') : view('auth.login');
    }

    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->scopes(['openid', 'email', 'profile'])
            ->with(['prompt' => 'select_account'])->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $g = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::warning('OAuth Google falló: '.$e->getMessage());

            return redirect()->route('login')->with('error', 'No se pudo completar el inicio de sesión con Google. Intente nuevamente.');
        }

        $usuario = Usuario::autorizado($g->getEmail());
        if (! $usuario) {
            return redirect()->route('acceso.denegado')->with('email', $g->getEmail());
        }

        $usuario->forceFill([
            'google_id' => $usuario->google_id ?: $g->getId(),
            'avatar' => $g->getAvatar() ?: $usuario->avatar,
            'ultimo_acceso_at' => now(),
        ])->save();

        Auth::login($usuario, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('panel'));
    }

    public function accesoDenegado(): View
    {
        return view('auth.acceso-denegado', ['email' => session('email')]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
