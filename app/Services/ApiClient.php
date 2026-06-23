<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ApiClient
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        // Se nutre exclusivamente de las variables de entorno del servidor
        $this->baseUrl = rtrim((string) config('monitor.fastapi_url'), '/');
        $this->apiKey  = (string) config('monitor.fastapi_key');
    }

    private function http()
    {
        // Forzamos un timeout de 10s para prevenir ataques de denegación de servicio (DoS) por agotamiento de recursos
        return Http::withToken($this->apiKey)->acceptJson()->timeout(10);
    }

    public function listarServidores(): array
    {
        // Comunicación Server-to-Server (Backend a Backend)
        $r = $this->http()->get("{$this->baseUrl}/api/admin/servidores");
        return ['status' => $r->status(), 'body' => $r->json() ?? []];
    }
}
