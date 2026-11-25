<?php

use App\Http\Controllers\Api\AgenteController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CasetaController;
use App\Http\Controllers\Api\HardwareController;
use App\Http\Controllers\Api\PasoController;
use App\Http\Controllers\Api\PlacaController;
use Illuminate\Support\Facades\Route;

/*
| API REST v1 — todas las rutas exigen Bearer token (Sanctum) emitido tras validar el Google ID Token,
| excepto /auth/google y /hardware/ultimo-evento (X-Hardware-Token).
*/
Route::prefix('v1')->group(function () {
    Route::post('auth/google', [AuthController::class, 'google'])->middleware('throttle:20,1');
    Route::get('hardware/ultimo-evento', [HardwareController::class, 'ultimoEvento'])
        ->middleware(['hardware.token', 'throttle:240,1']);

    Route::middleware(['auth:sanctum', 'activo'])->group(function () {
        Route::get('auth/yo', [AuthController::class, 'yo']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::post('pasos/frame', [PasoController::class, 'frame'])->middleware('throttle:600,1');
        Route::post('placas/consultar', [PlacaController::class, 'consultar']);
        Route::post('placas/consultar-imagen', [PlacaController::class, 'consultarImagen']);
        Route::get('placas/{placa}/historial', [PlacaController::class, 'historial'])->where('placa', '[A-Za-z0-9\-\s]+');
        Route::get('casetas', [CasetaController::class, 'index']);
        Route::get('casetas/{caseta}/resumen', [CasetaController::class, 'resumen']);
        Route::post('agente/chat', [AgenteController::class, 'chat'])->middleware('throttle:60,1');
    });
});
