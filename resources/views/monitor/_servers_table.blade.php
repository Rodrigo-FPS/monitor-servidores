<div class="table-responsive">
    <table class="table table-bordered table-hover align-middle" id="servers-table">
        <thead class="table-dark">
            <tr>
                <th><i class="fas fa-server me-1"></i>Hostname</th>
                <th><i class="fas fa-network-wired me-1"></i>Direccion IP</th>
                <th><i class="fas fa-info-circle me-1"></i>Estado</th>
                <th><i class="fas fa-heartbeat me-1"></i>Ultimo Latido</th>
                <th><i class="fas fa-cogs me-1"></i>Acciones</th>
            </tr>
        </thead>
        <tbody id="servers-table-body">
            @forelse($servers as $server)
                <tr>
                    <td><strong>{{ $server->hostname }}</strong></td>
                    <td><code>{{ $server->ip ?? $server->ip_address ?? 'N/A' }}</code></td>
                    <td>
                        @php
                            $status = $server->status ?? $server->estado ?? 'indeterminado';
                        @endphp
                        @if($status == 'encendido')
                            <span class="badge bg-success">
                                <i class="fas fa-check-circle me-1"></i>Encendido
                            </span>
                        @elseif($status == 'apagado')
                            <span class="badge bg-danger">
                                <i class="fas fa-power-off me-1"></i>Apagado
                            </span>
                        @else
                            <span class="badge bg-warning text-dark">
                                <i class="fas fa-question-circle me-1"></i>Indeterminado
                            </span>
                        @endif
                    </td>
                    <td>
                        @if(isset($server->last_heartbeat_at))
                            {{ $server->last_heartbeat_at->diffForHumans() }}
                        @elseif(isset($server->ultimo_visto))
                            @php
                                $ultimoVisto = \Carbon\Carbon::parse($server->ultimo_visto);
                            @endphp
                            {{ $ultimoVisto->diffForHumans() }}
                        @else
                            <span class="text-muted">Nunca</span>
                        @endif
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-secondary" disabled>
                            <i class="fas fa-eye"></i> Ver
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        <i class="fas fa-database me-2"></i>
                        No hay servidores registrados en el sistema.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>