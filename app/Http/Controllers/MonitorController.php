<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MonitorController extends Controller
{
    public function index()
    {
        $servers = [
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

        return view('monitor.index', compact('servers'));
    }

    public function getData()
    {
        // Datos de ejemplo (después vendrán de FastAPI)
        $servers = [
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

        return view('monitor._servers_table', compact('servers'));
    }
}