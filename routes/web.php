<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\MonitorController;

// Autenticacion
Route::get('/login',  [LoginController::class, 'mostrar'])->name('login');
Route::post('/login', [LoginController::class, 'autenticar']);
Route::post('/logout',[LoginController::class, 'cerrar'])->middleware('admin.auth');

// Panel de monitoreo protegido por sesion admin
Route::middleware('admin.auth')->group(function () {
    Route::get('/admin', [MonitorController::class, 'index']);
});

// Ruta para la pagina de monitorizacion de servidores
// Solo accesible para usuarios autenticados (administradores)
Route::get('/monitor', [App\Http\Controllers\MonitorController::class, 'index'])->name('monitor.index')->middleware('auth');

Route::get('/monitor/data', [MonitorController::class, 'getData'])->name('monitor.data')->middleware('auth');