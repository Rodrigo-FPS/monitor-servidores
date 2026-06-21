<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth('admin')->check()) { //redirige a login si no hay sesion activa en el guard admin
            return redirect()->route('login');
        }

        return $next($request);
    }
}
