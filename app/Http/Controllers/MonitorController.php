<?php

namespace App\Http\Controllers;

use App\Services\ApiClient;
use Illuminate\Support\Facades\Log;

class MonitorController extends Controller
{
    private ApiClient $api;

    public function __construct(ApiClient $api)
    {
        $this->api = $api;
    }

    /**
     * Muestra la pagina de monitorizacion con el estado de los servidores.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $servers = $this->getServersFromFastAPI();

        return view('monitor.index', compact('servers'));
    }

    /**
     * Obtiene los datos de los servidores desde FastAPI usando ApiClient.
     *
     * @return array
     */
    private function getServersFromFastAPI()
    {
        try {
            $resultado = $this->api->listarServidores();
            
            if ($resultado['status'] === 200) {
                $data = $resultado['body'];
                
                // FastAPI devuelve un array de objetos, lo mapeamos a un formato consistente
                return array_map(function($item) {
                    return (object) [
                        'hostname' => $item['hostname'] ?? 'Desconocido',
                        'ip' => $item['ip'] ?? '0.0.0.0',
                        'status' => $item['estado'] ?? 'indeterminado',
                        'ultimo_visto' => $item['ultimo_visto'] ?? null,
                    ];
                }, $data);
            }
            
            Log::warning('FastAPI respondio con error: ' . $resultado['status']);
            return [];
            
        } catch (\Exception $e) {
            Log::error('Error al conectar con FastAPI: ' . $e->getMessage());
            return [];
        }
    }
}