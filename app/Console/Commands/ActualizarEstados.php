<?php

namespace App\Console\Commands;

use App\Models\Servidor;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ActualizarEstados extends Command
{
    protected $signature = 'monitor:actualizar-estados';
    protected $description = '';

    public function handle(): void
    {
        $timeout = config('monitor.heartbeat_timeout');
        $limite  = Carbon::now()->subSeconds($timeout); //servidores sin latido antes de este momento quedan indeterminado

        Servidor::all()->each(function (Servidor $servidor) use ($limite) {
            if ($servidor->ultimo_tipo_mensaje === 'shutdown') {
                $servidor->estado = 'apagado';
            } elseif ($servidor->ultimo_visto === null) {
                $servidor->estado = 'indeterminado';
            } else {
                $servidor->estado = $servidor->ultimo_visto->gte($limite) ? 'encendido' : 'indeterminado';
            }
            $servidor->save();
        });
    }
}
