<?php

use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\AdminAuth;
use App\Http\Middleware\VerificarSesionEstricta; //Middleware de verificar sesiones en ../Middleware/VerificarSesionEstricta.php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SecurityHeaders::class); //aplica headers de seguridad en todas las respuestas
        $middleware->appendToGroup('web', [
            VerificarSesionEstricta::class,
        ]);
        $middleware->alias([
            'admin.auth' => AdminAuth::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/*', //los endpoints API usan HMAC-SHA256 en lugar de CSRF
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

// .env fuera del webroot: si no existe /etc/monitor-laravel/.env cae al directorio
// base del proyecto (compatibilidad con entornos de desarrollo)
if (is_dir('/etc/monitor-laravel')) {
    $app->useEnvironmentPath('/etc/monitor-laravel');
}

return $app;
