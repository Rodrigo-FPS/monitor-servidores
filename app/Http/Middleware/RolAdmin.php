<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RolAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('admin')->user();

        // Falla cerrado: sin usuario, sin rol, o rol distinto de 'admin' => denegado.
        if (! $user || $user->rol !== 'admin') {
            Log::channel('seguridad')->warning('acceso_denegado_rol', [
                'usuario' => $user?->username,
                'rol'     => $user?->rol,
                'metodo'  => $request->method(),
                'ruta'    => $request->path(),
                'ip'      => $request->ip(),
            ]);

            abort(403, 'No tiene permisos para realizar esta accion.');
        }

        return $next($request);
    }
}
