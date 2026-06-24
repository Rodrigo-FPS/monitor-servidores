<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Content-Security-Policy',
            // style-src 'self' (sin 'unsafe-inline') bloquea la inyeccion de <style>;
            // style-src-attr 'unsafe-inline' permite solo los atributos style en linea
            // que Bootstrap aplica via JS (modales, backdrops).
            "default-src 'self'; script-src 'self'; style-src 'self'; style-src-attr 'unsafe-inline'; font-src 'self'; img-src 'self' data:; frame-ancestors 'none';"
        );
        $response->headers->set(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains' //HSTS fuerza HTTPS por 1 año incluyendo subdominios
        );
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }
}
