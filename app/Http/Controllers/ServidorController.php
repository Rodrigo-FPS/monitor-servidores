<?php

namespace App\Http\Controllers;

use App\Services\ApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ServidorController extends Controller
{
    private const RE_SERVER_ID = '/^[a-zA-Z0-9_-]{1,64}$/';
    private const RE_HOSTNAME  = '/^[a-zA-Z0-9._-]{1,255}$/';

    private ApiClient $api;

    public function __construct(ApiClient $api)
    {
        $this->api = $api;
    }

    /** Registra una accion del administrador en el canal de auditoria. */
    private function auditar(string $accion, array $datos, Request $request): void
    {
        Log::channel('auditoria')->info($accion, array_merge($datos, [
            'admin' => Auth::guard('admin')->user()?->username,
            'ip'    => $request->ip(),
        ]));
    }

    /** Indica si la respuesta de la API representa exito (2xx). */
    private function exito(int $status): bool
    {
        return $status >= 200 && $status < 300;
    }

    // ── validadores de campo ──────────────────────────────────────────────────

    private function esServerIdValido(string $v): bool
    {
        return (bool) preg_match(self::RE_SERVER_ID, $v);
    }

    private function esHostnameValido(string $v): bool
    {
        return (bool) preg_match(self::RE_HOSTNAME, $v);
    }

    private function esIpValida(string $v): bool
    {
        return filter_var($v, FILTER_VALIDATE_IP) !== false; //acepta IPv4 e IPv6
    }

    // ── endpoints HTTP ────────────────────────────────────────────────────────

    public function index(): JsonResponse
    {
        try {
            $resultado = $this->api->listarServidores();
        } catch (\Exception) {
            return response()->json(['error' => 'no se pudo conectar con la API'], 503);
        }

        return response()->json($resultado['body'], $resultado['status']);
    }

    public function store(Request $request): JsonResponse
    {
        $serverId   = trim((string) $request->input('server_id', ''));
        $hostname   = trim((string) $request->input('hostname', ''));
        $ip         = trim((string) $request->input('ip', ''));
        $clavePub   = trim((string) $request->input('clave_publica', ''));

        if ($serverId === '' || $hostname === '' || $ip === '' || $clavePub === '') {
            return response()->json(['error' => 'se requieren server_id hostname ip clave_publica'], 400);
        }

        if (strlen($serverId) > 64) {
            return response()->json(['error' => 'server_id demasiado largo maximo 64 chars'], 422);
        }
        if (strlen($hostname) > 255) {
            return response()->json(['error' => 'hostname demasiado largo maximo 255 chars'], 422);
        }
        if (strlen($ip) > 45) {
            return response()->json(['error' => 'ip demasiado larga'], 422);
        }
        if (strlen($clavePub) > 800 || !str_starts_with($clavePub, '-----BEGIN PUBLIC KEY-----')) {
            return response()->json(['error' => 'clave_publica debe ser un PEM Ed25519 valido'], 422);
        }

        if (!$this->esServerIdValido($serverId)) {
            return response()->json(['error' => 'server_id invalido solo alfanumericos guion y guion bajo'], 422);
        }
        if (!$this->esHostnameValido($hostname)) {
            return response()->json(['error' => 'hostname invalido'], 422);
        }
        if (!$this->esIpValida($ip)) {
            return response()->json(['error' => 'ip invalida debe ser IPv4 o IPv6 valida'], 422);
        }

        try {
            $resultado = $this->api->registrarServidor($serverId, $hostname, $ip, $clavePub);
        } catch (\Exception) {
            return response()->json(['error' => 'no se pudo conectar con la API'], 503);
        }

        if ($this->exito($resultado['status'])) {
            $this->auditar('servidor_creado', [
                'server_id' => $serverId,
                'hostname'  => $hostname,
                'ip_nueva'  => $ip,
            ], $request);
        }

        return response()->json($resultado['body'], $resultado['status']);
    }

    public function update(Request $request, string $serverId): JsonResponse
    {
        $serverId = trim($serverId);

        if ($serverId === '' || strlen($serverId) > 64 || !$this->esServerIdValido($serverId)) {
            return response()->json(['error' => 'server_id invalido'], 422);
        }

        $ip = trim((string) $request->input('ip', ''));

        if ($ip === '') {
            return response()->json(['error' => 'se requiere el campo ip'], 400);
        }
        if (strlen($ip) > 45) {
            return response()->json(['error' => 'ip demasiado larga'], 422);
        }
        if (!$this->esIpValida($ip)) {
            return response()->json(['error' => 'ip invalida'], 422);
        }

        try {
            $resultado = $this->api->actualizarServidor($serverId, $ip);
        } catch (\Exception) {
            return response()->json(['error' => 'no se pudo conectar con la API'], 503);
        }

        if ($this->exito($resultado['status'])) {
            $this->auditar('servidor_actualizado', [
                'server_id' => $serverId,
                'ip_nueva'  => $ip,
            ], $request);
        }

        return response()->json($resultado['body'], $resultado['status']);
    }

    public function destroy(Request $request, string $serverId): JsonResponse
    {
        $serverId = trim($serverId);

        if ($serverId === '' || strlen($serverId) > 64 || !$this->esServerIdValido($serverId)) {
            return response()->json(['error' => 'server_id invalido'], 422);
        }

        try {
            $resultado = $this->api->eliminarServidor($serverId);
        } catch (\Exception) {
            return response()->json(['error' => 'no se pudo conectar con la API'], 503);
        }

        if ($this->exito($resultado['status'])) {
            $this->auditar('servidor_eliminado', [
                'server_id' => $serverId,
            ], $request);
        }

        return response()->json($resultado['body'], $resultado['status']);
    }
}
