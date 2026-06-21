<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HeartbeatController;
use App\Http\Controllers\ShutdownController;
use App\Http\Controllers\ServidorController;

//latidos autenticados con HMAC-SHA256 en los controllers
Route::post('/heartbeat', [HeartbeatController::class, 'recibir']);
Route::post('/shutdown',  [ShutdownController::class, 'recibir']);

//gestion de servidores registrados protegida por sesion admin
Route::middleware('admin.auth')->group(function () {
    Route::get('/servidores',               [ServidorController::class, 'index']);
    Route::post('/servidores',              [ServidorController::class, 'store']);
    Route::patch('/servidores/{server_id}', [ServidorController::class, 'update']);
    Route::delete('/servidores/{server_id}',[ServidorController::class, 'destroy']);
});
