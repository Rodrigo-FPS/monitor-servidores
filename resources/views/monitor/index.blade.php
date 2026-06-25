@extends('layouts.app')

@section('title', 'Monitor de Servidores')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card shadow-lg">
            <div class="card-header bg-primary text-white">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h1 class="h5 card-title mb-0">
                        <i class="fas fa-server me-2" aria-hidden="true"></i>Estado de Servidores
                    </h1>
                    <div class="d-flex flex-wrap align-items-center gap-2" aria-label="Leyenda de estados">
                        <span class="badge bg-success"><i class="fas fa-circle me-1" aria-hidden="true"></i>Encendido</span>
                        <span class="badge bg-danger"><i class="fas fa-circle me-1" aria-hidden="true"></i>Apagado</span>
                        <span class="badge bg-warning text-dark"><i class="fas fa-circle me-1" aria-hidden="true"></i>Indeterminado</span>
                        @if(auth('admin')->user()?->esAdmin())
                        <button type="button" class="btn btn-light btn-sm ms-1"
                                data-bs-toggle="modal" data-bs-target="#modal-agregar">
                            <i class="fas fa-plus me-1" aria-hidden="true"></i>Agregar servidor
                        </button>
                        @endif
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div id="servers-table-container">
                    @include('monitor._servers_table')
                </div>
            </div>
            <div class="card-footer bg-light text-muted d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <i class="fas fa-sync-alt me-1" aria-hidden="true"></i>
                    Última actualización: <span id="last-update-time" aria-live="polite">{{ now()->format('H:i:s') }}</span>
                </div>
                <div>
                    <span class="badge bg-info text-dark">
                        <i class="fas fa-clock me-1" aria-hidden="true"></i>Auto-refresh cada 10s
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

@if(auth('admin')->user()?->esAdmin())
<!-- Modales de gestion: solo visibles para el rol admin -->
<!-- Modal: Agregar Servidor -->
<div class="modal fade" id="modal-agregar" tabindex="-1" aria-labelledby="modal-agregar-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="h5 modal-title" id="modal-agregar-label">
                    <i class="fas fa-plus me-2" aria-hidden="true"></i>Agregar Servidor
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="agregar-error" class="alert alert-danger d-none" role="alert"></div>

                <div class="mb-3">
                    <label for="agregar-server-id" class="form-label fw-semibold">ID del Servidor <span class="text-danger" aria-hidden="true">*</span></label>
                    <input type="text" class="form-control" id="agregar-server-id"
                           placeholder="ej: servidor-web-01" maxlength="64"
                           required aria-required="true"
                           aria-describedby="agregar-server-id-hint"
                           pattern="[a-zA-Z0-9_-]+">
                    <div class="form-text" id="agregar-server-id-hint">Solo letras, números, guión y guión bajo.</div>
                </div>
                <div class="mb-3">
                    <label for="agregar-hostname" class="form-label fw-semibold">Hostname <span class="text-danger" aria-hidden="true">*</span></label>
                    <input type="text" class="form-control" id="agregar-hostname"
                           placeholder="ej: debian-web-01" maxlength="255"
                           required aria-required="true">
                </div>
                <div class="mb-3">
                    <label for="agregar-ip" class="form-label fw-semibold">Dirección IP <span class="text-danger" aria-hidden="true">*</span></label>
                    <input type="text" class="form-control" id="agregar-ip"
                           placeholder="ej: 192.168.10.20" maxlength="45"
                           required aria-required="true"
                           aria-describedby="agregar-ip-hint">
                    <div class="form-text" id="agregar-ip-hint">IPv4 o IPv6.</div>
                </div>
                <div class="mb-3">
                    <label for="agregar-clave-publica" class="form-label fw-semibold">Clave Pública Ed25519 <span class="text-danger" aria-hidden="true">*</span></label>
                    <textarea class="form-control font-monospace small" id="agregar-clave-publica"
                              rows="4" placeholder="-----BEGIN PUBLIC KEY-----&#10;...&#10;-----END PUBLIC KEY-----" maxlength="800"
                              required aria-required="true"
                              aria-describedby="agregar-clave-hint"></textarea>
                    <div class="form-text" id="agregar-clave-hint">
                        Arranca el agente una vez en el cliente para generarla, luego copia
                        el contenido de <code>/etc/monitor-agent/public.key</code>.
                    </div>
                </div>
                <p class="form-text mb-0"><span class="text-danger" aria-hidden="true">*</span> Campos obligatorios</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-confirmar-agregar">
                    <i class="fas fa-plus me-1" aria-hidden="true"></i>Agregar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Editar Servidor -->
<div class="modal fade" id="modal-editar" tabindex="-1" aria-labelledby="modal-editar-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="h5 modal-title" id="modal-editar-label">
                    <i class="fas fa-edit me-2" aria-hidden="true"></i>Editar Servidor
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="editar-error" class="alert alert-danger d-none" role="alert"></div>
                <div class="mb-3">
                    <label for="editar-server-id-display" class="form-label fw-semibold">ID del Servidor</label>
                    <input type="text" class="form-control" id="editar-server-id-display"
                           disabled aria-disabled="true" aria-label="ID del servidor (no editable)">
                </div>
                <div class="mb-3">
                    <label for="editar-ip" class="form-label fw-semibold">Dirección IP <span class="text-danger" aria-hidden="true">*</span></label>
                    <input type="text" class="form-control" id="editar-ip" maxlength="45"
                           required aria-required="true">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-confirmar-editar">
                    <i class="fas fa-save me-1" aria-hidden="true"></i>Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Eliminar Servidor -->
<div class="modal fade" id="modal-eliminar" tabindex="-1" aria-labelledby="modal-eliminar-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h2 class="h5 modal-title" id="modal-eliminar-label">
                    <i class="fas fa-trash me-2" aria-hidden="true"></i>Eliminar Servidor
                </h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="eliminar-error" class="alert alert-danger d-none" role="alert"></div>
                <p>¿Estás seguro de que deseas eliminar el servidor <strong id="eliminar-server-id-display"></strong>?</p>
                <p class="text-danger small mb-0"><i class="fas fa-exclamation-triangle me-1" aria-hidden="true"></i>Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btn-confirmar-eliminar">
                    <i class="fas fa-trash me-1" aria-hidden="true"></i>Eliminar
                </button>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script src="{{ asset('js/monitor.js') }}"></script>
@endpush
