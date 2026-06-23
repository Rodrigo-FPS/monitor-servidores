<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ApiClient
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('monitor.fastapi_url'), '/');
        $this->apiKey  = (string) config('monitor.fastapi_key');
    }

    private function http() //cliente HTTP con autenticacion Bearer y timeout fijo
    {
        return Http::withToken($this->apiKey)->acceptJson()->timeout(10);
    }

    public function listarServidores(): array
    {
        $r = $this->http()->get("{$this->baseUrl}/api/admin/servidores");
        return ['status' => $r->status(), 'body' => $r->json() ?? []];
    }

    public function registrarServidor(string $serverId, string $hostname, string $ip): array
    {
        $r = $this->http()->post("{$this->baseUrl}/api/admin/servidores", [
            'server_id' => $serverId,
            'hostname'  => $hostname,
            'ip'        => $ip,
        ]);
        return ['status' => $r->status(), 'body' => $r->json() ?? []];
    }

    public function actualizarServidor(string $serverId, string $ip): array
    {
        $r = $this->http()->patch("{$this->baseUrl}/api/admin/servidores/{$serverId}", [
            'ip' => $ip,
        ]);
        return ['status' => $r->status(), 'body' => $r->json() ?? []];
    }

    public function eliminarServidor(string $serverId): array
    {
        $r = $this->http()->delete("{$this->baseUrl}/api/admin/servidores/{$serverId}");
        return ['status' => $r->status(), 'body' => $r->json() ?? []];
    }
}
