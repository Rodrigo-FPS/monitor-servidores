<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MonitorController extends Controller
{
    /**
     * Muestra la página de monitorización con el estado de los servidores.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Obtener datos de FastAPI
        $servers = $this->getServersFromFastAPI();

        // Si FastAPI no responde, usar datos de ejemplo
        if (empty($servers)) {
            $servers = $this->getExampleData();
        }

        return view('monitor.index', compact('servers'));
    }

    /**
     * Obtiene los datos de los servidores desde FastAPI.
     *
     * @return array
     */
    private function getServersFromFastAPI()
    {
        try {
            $fastapiUrl = env('FASTAPI_URL');
            $fastapiKey = env('FASTAPI_KEY');

            // Si no hay URL configurada, devolver datos de ejemplo
            if (empty($fastapiUrl)) {
                return [];
            }

            $response = Http::withHeaders([
                'X-API-Key' => $fastapiKey
            ])->timeout(5)->get($fastapiUrl . '/servers/status');

            if ($response->successful()) {
                return $response->json();
            }

            return [];
        } catch (\Exception $e) {
            // Si hay error, devolver datos de ejemplo
            return [];
        }
    }

    /**
     * Devuelve datos de ejemplo para desarrollo.
     *
     * @return array
     */
    private function getExampleData()
    {
        return [
            (object) [
                'hostname' => 'servidor-web-01',
                'ip_address' => '0.0.0.0', //modificar en produccion
                'status' => 'encendido',
                'last_heartbeat_at' => now()->subMinutes(2)
            ],
            (object) [
                'hostname' => 'servidor-db-02',
                'ip_address' => '0.0.0.0', //modificar en produccion
                'status' => 'apagado',
                'last_heartbeat_at' => now()->subHours(1)
            ],
            (object) [
                'hostname' => 'servidor-app-03',
                'ip_address' => '0.0.0.0', //modificar en produccion
                'status' => 'indeterminado',
                'last_heartbeat_at' => now()->subMinutes(15)
            ],
        ];
    }

    /**
     * Devuelve solo la tabla de servidores para AJAX.
     *
     * @return \Illuminate\View\View
     */
    public function getData()
    {
        // Obtener datos de FastAPI
        $servers = $this->getServersFromFastAPI();

        // Si FastAPI no responde, usar datos de ejemplo
        if (empty($servers)) {
            $servers = $this->getExampleData();
        }

        return view('monitor._servers_table', compact('servers'));
    }
}