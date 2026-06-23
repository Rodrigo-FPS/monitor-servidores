@extends('layouts.app')

@section('title', 'Monitor de Servidores')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card shadow-lg">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-server me-2"></i>Estado de Servidores
                </h5>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success"><i class="fas fa-circle me-1"></i>Encendido</span>
                    <span class="badge bg-danger"><i class="fas fa-circle me-1"></i>Apagado</span>
                    <span class="badge bg-warning text-dark"><i class="fas fa-circle me-1"></i>Indeterminado</span>
                    <button type="button" class="btn btn-light btn-sm ms-1"
                            data-bs-toggle="modal" data-bs-target="#modal-agregar">
                        <i class="fas fa-plus me-1"></i>Agregar
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="servers-table-container">
                    @include('monitor._servers_table')
                </div>
            </div>
            <div class="card-footer bg-light text-muted d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-sync-alt me-1"></i>
                    Ultima actualizacion: <span id="last-update-time">{{ now()->format('H:i:s') }}</span>
                </div>
                <div>
                    <span class="badge bg-info text-dark">
                        <i class="fas fa-clock me-1"></i>Auto-refresh cada 10s
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Agregar Servidor -->
<div class="modal fade" id="modal-agregar" tabindex="-1" aria-labelledby="modal-agregar-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-agregar-label">
                    <i class="fas fa-plus me-2"></i>Agregar Servidor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="agregar-error" class="alert alert-danger d-none"></div>
                <div class="mb-3">
                    <label for="agregar-server-id" class="form-label fw-semibold">ID del Servidor</label>
                    <input type="text" class="form-control" id="agregar-server-id"
                           placeholder="ej: servidor-web-01" maxlength="64">
                    <div class="form-text">Solo letras, números, guión y guión bajo.</div>
                </div>
                <div class="mb-3">
                    <label for="agregar-hostname" class="form-label fw-semibold">Hostname</label>
                    <input type="text" class="form-control" id="agregar-hostname"
                           placeholder="ej: debian-web-01" maxlength="255">
                </div>
                <div class="mb-3">
                    <label for="agregar-ip" class="form-label fw-semibold">Dirección IP</label>
                    <input type="text" class="form-control" id="agregar-ip"
                           placeholder="ej: 192.168.10.20" maxlength="45">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-confirmar-agregar">
                    <i class="fas fa-plus me-1"></i>Agregar
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
                <h5 class="modal-title" id="modal-editar-label">
                    <i class="fas fa-edit me-2"></i>Editar Servidor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="editar-error" class="alert alert-danger d-none"></div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">ID del Servidor</label>
                    <input type="text" class="form-control" id="editar-server-id-display" disabled>
                </div>
                <div class="mb-3">
                    <label for="editar-ip" class="form-label fw-semibold">Dirección IP</label>
                    <input type="text" class="form-control" id="editar-ip" maxlength="45">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-confirmar-editar">
                    <i class="fas fa-save me-1"></i>Guardar
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
                <h5 class="modal-title" id="modal-eliminar-label">
                    <i class="fas fa-trash me-2"></i>Eliminar Servidor
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="eliminar-error" class="alert alert-danger d-none"></div>
                <p>¿Estás seguro de que deseas eliminar el servidor <strong id="eliminar-server-id-display"></strong>?</p>
                <p class="text-muted small mb-0">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btn-confirmar-eliminar">
                    <i class="fas fa-trash me-1"></i>Eliminar
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/monitor.js') }}"></script>
@endpush
