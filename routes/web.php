<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\ServidorController;

// Autenticacion
Route::get('/login',  [LoginController::class, 'mostrar'])->name('login');
Route::post('/login', [LoginController::class, 'autenticar']);
Route::post('/logout',[LoginController::class, 'cerrar'])->middleware('admin.auth')->name('logout');

// Panel de monitoreo protegido por sesion admin
Route::middleware('admin.auth')->group(function () {
    Route::get('/admin', [MonitorController::class, 'index']);
});

// Ruta para la pagina de monitorizacion de servidores
// Solo accesible para usuarios autenticados (administradores)
Route::get('/monitor', [App\Http\Controllers\MonitorController::class, 'index'])->name('monitor.index')->middleware('auth');

Route::get('/monitor/data', [MonitorController::class, 'getData'])->name('monitor.data')->middleware('auth');

// Gateway hacia FastAPI — en web.php para que el guard session funcione con admin.auth
Route::middleware('admin.auth')->prefix('api/admin')->group(function () {
    Route::get('/servidores',               [ServidorController::class, 'index']);
    Route::post('/servidores',              [ServidorController::class, 'store']);
    Route::patch('/servidores/{server_id}', [ServidorController::class, 'update']);
    Route::delete('/servidores/{server_id}',[ServidorController::class, 'destroy']);
});