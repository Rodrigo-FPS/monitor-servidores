<table class="table table-bordered table-hover" id="servers-table">
    <thead class="table-dark">
        <tr>
            <th>Hostname</th>
            <th>Direccion IP</th>
            <th>Estado</th>
            <th>Ultimo Latido</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody id="servers-table-body">
        @forelse($servers as $server)
            <tr>
                <td><strong>{{ $server->hostname }}</strong></td>
                <td><code>{{ $server->ip_address }}</code></td>
                <td>
                    @if($server->status == 'encendido')
                        <span class="badge bg-success">Encendido</span>
                    @elseif($server->status == 'apagado')
                        <span class="badge bg-danger">Apagado</span>
                    @else
                        <span class="badge bg-warning text-dark">Indeterminado</span>
                    @endif
                </td>
                <td>
                    @if($server->last_heartbeat_at)
                        {{ $server->last_heartbeat_at->diffForHumans() }}
                    @else
                        Nunca
                    @endif
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-info">Ver</button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center">No hay servidores registrados.</td>
            </tr>
        @endforelse
    </tbody>
</table>