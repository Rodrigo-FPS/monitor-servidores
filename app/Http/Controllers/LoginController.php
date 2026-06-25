<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginAdminRequest;
use App\Models\IntentoLoginFallido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LoginController extends Controller
{
    public function mostrar()
    {
        return view('auth.login'); // ASEGURENSE DE TENER UNA VISTA resources/views/auth/login.blade.php
    }

    public function autenticar(LoginAdminRequest $request)
    {
        $ip = $request->ip();
        // utiliza los valores de la configuracion
        $maxIntentos = config('monitor.login_max_intentos');
        $ventanaMinutos = config('monitor.login_ventana_min');

        // para la defensa contra fuerza bruta
        $intentos = IntentoLoginFallido::where('ip', $ip)
            ->where('created_at', '>=', Carbon::now()->subMinutes($ventanaMinutos))
            ->count();

        if ($intentos >= $maxIntentos) {
            Log::channel('seguridad')->warning('login_bloqueado_fuerza_bruta', [
                'ip'       => $ip,
                'username' => $request->username,
            ]);
            // no dar pistas de si el usuario existe o no, bloquear por IP temporalmente
            return back()->withErrors([
                'auth' => 'Múltiples intentos fallidos detectados. Por seguridad, su IP ha sido bloqueada temporalmente.'
            ]);
        }

        $credenciales = $request->only('username', 'password');

        if (Auth::guard('admin')->attempt($credenciales)) {
            // Prevención de session fixation: Generar un nuevo ID de sesion seguro
            $request->session()->regenerate();

            // Pinning de sesionn: Guardar huella digital para el middleware anti-hijacking
            $request->session()->put('vinculo_ip', $request->ip());
            $request->session()->put('vinculo_ua', $request->userAgent());

            Log::channel('auditoria')->info('login_exitoso', [
                'username' => $request->username,
                'ip'       => $ip,
            ]);

            return redirect()->intended('/admin');
        }

        // Registrar el fallo
        IntentoLoginFallido::create([
            'ip' => $ip,
            'username' => $request->username
        ]);

        Log::channel('seguridad')->info('login_fallido', [
            'ip'       => $ip,
            'username' => $request->username,
        ]);

        return back()->withErrors([
            'auth' => 'Las credenciales proporcionadas no son válidas.'
        ]);
    }

    public function cerrar(Request $request)
    {
        Auth::guard('admin')->logout();

        // destruccion de variables de sesion
        $request->session()->invalidate();
        
        // prevencion de ataques CSRF en post-deslogueo
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
