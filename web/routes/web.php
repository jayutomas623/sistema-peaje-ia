<?php

use App\Http\Controllers\AlertaController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\CasetaController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\EstadoController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\PasoController;
use App\Http\Controllers\TarifaController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VehiculoController;
use Illuminate\Support\Facades\Route;

// ---------------- Autenticación (solo Google) ----------------
Route::middleware('guest')->group(function () {
    Route::get('/login', [GoogleController::class, 'login'])->name('login');
    Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');
});
Route::get('/acceso-denegado', [GoogleController::class, 'accesoDenegado'])->name('acceso.denegado');
Route::post('/logout', [GoogleController::class, 'logout'])->middleware('auth')->name('logout');

// ---------------- Panel (administrador y operador) ----------------
Route::middleware(['auth', 'activo'])->group(function () {
    Route::get('/', [PanelController::class, 'index'])->name('panel');
    Route::get('/panel/feed', [PanelController::class, 'feed'])->name('panel.feed');

    Route::get('/pasos', [PasoController::class, 'index'])->name('pasos.index');
    Route::post('/pasos', [PasoController::class, 'store'])->name('pasos.store');

    Route::get('/alertas', [AlertaController::class, 'index'])->name('alertas.index');
    Route::post('/alertas/{alerta}/atender', [AlertaController::class, 'atender'])->name('alertas.atender');

    Route::get('/chat', [ChatController::class, 'index'])->name('chat');
    Route::post('/chat/enviar', [ChatController::class, 'enviar'])->middleware('throttle:60,1')->name('chat.enviar');

    Route::get('/estado', [EstadoController::class, 'index'])->name('estado');
    Route::get('/estado/health', [EstadoController::class, 'health'])->name('estado.health');

    // Vehículos: el operador consulta; solo el administrador crea, edita y elimina
    Route::get('/vehiculos', [VehiculoController::class, 'index'])->name('vehiculos.index');

    // ---------------- Solo administrador ----------------
    Route::middleware('rol:administrador')->group(function () {
        Route::resource('vehiculos', VehiculoController::class)->except(['index', 'show']);
        Route::resource('tarifas', TarifaController::class)->except(['show']);
        Route::resource('casetas', CasetaController::class)->except(['show']);
        Route::resource('usuarios', UsuarioController::class)->except(['show']);
        Route::post('/estado', [EstadoController::class, 'guardar'])->name('estado.guardar');
    });

    Route::get('/vehiculos/{vehiculo}', [VehiculoController::class, 'show'])->name('vehiculos.show');
});
