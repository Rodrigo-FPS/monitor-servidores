<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\ServidorController;

// Raiz redirige al login
Route::get('/', fn() => redirect('/login'));

// Autenticacion
Route::get('/login',  [LoginController::class, 'mostrar'])->name('login');
Route::post('/login', [LoginController::class, 'autenticar']);
Route::post('/logout',[LoginController::class, 'cerrar'])->middleware('admin.auth')->name('logout');

// Area autenticada del panel (ambos roles: admin y observador)
Route::middleware('admin.auth')->group(function () {
    // Pagina de monitoreo con el estado de los servidores
    Route::get('/admin', [MonitorController::class, 'index'])->name('monitor.index');

    // Gateway hacia FastAPI — en web.php para que el guard de sesion funcione
    Route::prefix('api/admin')->group(function () {
        // Lectura del estado: accesible para ambos roles
        Route::get('/servidores', [ServidorController::class, 'index']);

        // Escritura: solo el rol admin (rol.admin corre despues de admin.auth)
        Route::middleware('rol.admin')->group(function () {
            Route::post('/servidores',               [ServidorController::class, 'store']);
            Route::patch('/servidores/{server_id}',  [ServidorController::class, 'update']);
            Route::delete('/servidores/{server_id}', [ServidorController::class, 'destroy']);
        });
    });
});
