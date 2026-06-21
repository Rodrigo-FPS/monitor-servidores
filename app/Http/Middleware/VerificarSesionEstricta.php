<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerificarSesionEstricta
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('admin')->check()) {
            $sesionIp = $request->session()->get('vinculo_ip');
            $sesionUa = $request->session()->get('vinculo_ua');

            // Si la IP o el User agent cambian durante una sesion activa, esta se intercepta y destruye
            if ($sesionIp !== $request->ip() || $sesionUa !== $request->userAgent()) {
                Auth::guard('admin')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                
                abort(403, 'Alerta de seguridad: Posible secuestro de sesion o intento. Sesion terminada.');
            }
        }

        return $next($request);
    }
}
