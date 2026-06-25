<div class="table-responsive">
    <table class="table table-bordered table-hover align-middle" id="servers-table">
        <caption class="visually-hidden">Lista de servidores monitoreados con su estado actual y último latido</caption>
        <thead class="table-dark">
            <tr>
                <th scope="col"><i class="fas fa-server me-1" aria-hidden="true"></i>Hostname</th>
                <th scope="col"><i class="fas fa-network-wired me-1" aria-hidden="true"></i>Dirección IP</th>
                <th scope="col"><i class="fas fa-info-circle me-1" aria-hidden="true"></i>Estado</th>
                <th scope="col"><i class="fas fa-heartbeat me-1" aria-hidden="true"></i>Último Latido</th>
                <th scope="col"><i class="fas fa-cogs me-1" aria-hidden="true"></i>Acciones</th>
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
                                <i class="fas fa-check-circle me-1" aria-hidden="true"></i>Encendido
                            </span>
                        @elseif($status == 'apagado')
                            <span class="badge bg-danger">
                                <i class="fas fa-power-off me-1" aria-hidden="true"></i>Apagado
                            </span>
                        @else
                            <span class="badge bg-warning text-dark">
                                <i class="fas fa-question-circle me-1" aria-hidden="true"></i>Indeterminado
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
                        @if(auth('admin')->user()?->esAdmin())
                            <button class="btn btn-sm btn-outline-primary me-1 btn-editar-servidor"
                                    data-server-id="{{ $server->server_id }}"
                                    data-ip="{{ $server->ip ?? $server->ip_address ?? '' }}"
                                    aria-label="Editar {{ $server->hostname }}">
                                <i class="fas fa-edit" aria-hidden="true"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger btn-eliminar-servidor"
                                    data-server-id="{{ $server->server_id }}"
                                    aria-label="Eliminar {{ $server->hostname }}">
                                <i class="fas fa-trash" aria-hidden="true"></i>
                            </button>
                        @else
                            <span class="text-muted small">Solo lectura</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        <i class="fas fa-database me-2" aria-hidden="true"></i>
                        No hay servidores registrados en el sistema.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
