<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ServidorController;

//gestion de servidores — protegida por sesion admin
//los endpoints /api/heartbeat y /api/shutdown los maneja FastAPI directamente via Nginx
Route::middleware('admin.auth')->prefix('admin')->group(function () {
    Route::get('/servidores',               [ServidorController::class, 'index']);
    Route::post('/servidores',              [ServidorController::class, 'store']);
    Route::patch('/servidores/{server_id}', [ServidorController::class, 'update']);
    Route::delete('/servidores/{server_id}',[ServidorController::class, 'destroy']);
});
