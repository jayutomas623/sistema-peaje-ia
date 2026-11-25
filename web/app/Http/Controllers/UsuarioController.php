<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Lista blanca de cuentas de Google (solo administrador). */
class UsuarioController extends Controller
{
    public function index(): View
    {
        return view('usuarios.index', ['usuarios' => Usuario::orderBy('rol')->orderBy('nombre')->get()]);
    }

    public function create(): View
    {
        return view('usuarios.form', ['usuario' => new Usuario(['rol' => Usuario::ROL_OPERADOR, 'activo' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Usuario::create($this->validar($request));

        return redirect()->route('usuarios.index')->with('ok', 'Correo agregado a la lista blanca.');
    }

    public function edit(Usuario $usuario): View
    {
        return view('usuarios.form', compact('usuario'));
    }

    public function update(Request $request, Usuario $usuario): RedirectResponse
    {
        $datos = $this->validar($request, $usuario);
        if ($usuario->is($request->user()) && ($datos['rol'] !== Usuario::ROL_ADMIN || ! $datos['activo'])) {
            return back()->with('error', 'No puede quitarse a sí mismo el rol de administrador ni desactivarse.');
        }
        $usuario->update($datos);
        if (! $usuario->activo) {
            $usuario->tokens()->delete();   // revoca tokens de la app móvil
        }

        return redirect()->route('usuarios.index')->with('ok', 'Usuario actualizado.');
    }

    public function destroy(Request $request, Usuario $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return back()->with('error', 'No puede eliminar su propia cuenta.');
        }
        if (mb_strtolower($usuario->email) === mb_strtolower(config('peaje.docente_email'))) {
            return back()->with('error', 'La cuenta del docente evaluador no puede eliminarse.');
        }
        $usuario->tokens()->delete();
        $usuario->delete();

        return redirect()->route('usuarios.index')->with('ok', 'Usuario eliminado de la lista blanca.');
    }

    private function validar(Request $request, ?Usuario $u = null): array
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email'))), 'activo' => $request->boolean('activo')]);

        return $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('usuarios')->ignore($u?->id)],
            'rol' => ['required', Rule::in(Usuario::ROLES)],
            'activo' => ['boolean'],
        ]);
    }
}
