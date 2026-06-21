<?php

namespace App\Http\Controllers;

use App\Models\Servidor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServidorController extends Controller
{
    private const RE_SERVER_ID = '/^[a-zA-Z0-9_-]{1,64}$/';
    private const RE_HOSTNAME  = '/^[a-zA-Z0-9._-]{1,255}$/';

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

    // ── logica de negocio sin dependencia de HTTP ─────────────────────────────

    private function listar(): array
    {
        return Servidor::orderBy('hostname')
            ->get()
            ->map(fn($s) => [
                'server_id'     => $s->server_id,
                'hostname'      => $s->hostname,
                'ip'            => $s->ip_registrada,
                'estado'        => $s->estado,
                'ultimo_visto'  => $s->ultimo_visto?->toIso8601String(),
                'registrado_en' => $s->registrado_en->toIso8601String(),
            ])
            ->all();
    }

    private function registrar(string $serverId, string $hostname, string $ip): array
    {
        if (Servidor::where('server_id', $serverId)->exists()) {
            return ['ok' => false, 'codigo' => 409, 'error' => 'el server_id ya existe'];
        }

        $secreto = bin2hex(random_bytes(32)); //64 hex chars criptograficamente seguros para HMAC-SHA256

        Servidor::create([
            'server_id'     => $serverId,
            'hostname'      => $hostname,
            'ip_registrada' => $ip,
            'secreto'       => $secreto,
            'estado'        => 'indeterminado',
            'registrado_en' => now(),
        ]);

        return ['ok' => true, 'server_id' => $serverId, 'secreto' => $secreto];
    }

    private function actualizarIp(string $serverId, string $nuevaIp): array
    {
        $servidor = Servidor::find($serverId);
        if ($servidor === null) {
            return ['ok' => false, 'codigo' => 404, 'error' => 'servidor no encontrado'];
        }

        $servidor->ip_registrada = $nuevaIp;
        $servidor->save();

        return ['ok' => true];
    }

    private function eliminar(string $serverId): array
    {
        $servidor = Servidor::find($serverId);
        if ($servidor === null) {
            return ['ok' => false, 'codigo' => 404, 'error' => 'servidor no encontrado'];
        }

        $servidor->delete();
        return ['ok' => true];
    }

    // ── endpoints HTTP ────────────────────────────────────────────────────────

    public function index(): JsonResponse
    {
        return response()->json($this->listar());
    }

    public function store(Request $request): JsonResponse
    {
        $serverId = trim((string) $request->input('server_id', ''));
        $hostname = trim((string) $request->input('hostname', ''));
        $ip       = trim((string) $request->input('ip', ''));

        if ($serverId === '' || $hostname === '' || $ip === '') {
            return response()->json(['error' => 'se requieren server_id hostname ip'], 400);
        }

        //verificar longitud antes del regex para prevenir ReDoS
        if (strlen($serverId) > 64) {
            return response()->json(['error' => 'server_id demasiado largo maximo 64 chars'], 422);
        }
        if (strlen($hostname) > 255) {
            return response()->json(['error' => 'hostname demasiado largo maximo 255 chars'], 422);
        }
        if (strlen($ip) > 45) {
            return response()->json(['error' => 'ip demasiado larga'], 422);
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
            $resultado = $this->registrar($serverId, $hostname, $ip);
        } catch (\Exception) {
            return response()->json(['error' => 'error interno al registrar servidor'], 500);
        }

        if (!$resultado['ok']) {
            return response()->json(['error' => $resultado['error']], $resultado['codigo']);
        }

        return response()->json([
            'server_id' => $resultado['server_id'],
            'secreto'   => $resultado['secreto'], //unica vez que se devuelve el secreto
            'aviso'     => 'copia el secreto ahora no se puede recuperar despues',
        ], 201);
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
            $resultado = $this->actualizarIp($serverId, $ip);
        } catch (\Exception) {
            return response()->json(['error' => 'error interno al actualizar servidor'], 500);
        }

        if (!$resultado['ok']) {
            return response()->json(['error' => $resultado['error']], $resultado['codigo']);
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(string $serverId): JsonResponse
    {
        $serverId = trim($serverId);

        if ($serverId === '' || strlen($serverId) > 64 || !$this->esServerIdValido($serverId)) {
            return response()->json(['error' => 'server_id invalido'], 422);
        }

        try {
            $resultado = $this->eliminar($serverId);
        } catch (\Exception) {
            return response()->json(['error' => 'error interno al eliminar servidor'], 500);
        }

        if (!$resultado['ok']) {
            return response()->json(['error' => $resultado['error']], $resultado['codigo']);
        }

        return response()->json(['ok' => true]);
    }
}
