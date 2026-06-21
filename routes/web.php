<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\MonitorController;

//autenticacion
Route::get('/login',  [LoginController::class, 'mostrar'])->name('login');
Route::post('/login', [LoginController::class, 'autenticar']);
Route::post('/logout',[LoginController::class, 'cerrar'])->middleware('admin.auth');

//panel de monitoreo protegido por sesion admin
Route::middleware('admin.auth')->group(function () {
    Route::get('/admin', [MonitorController::class, 'index']);
});
