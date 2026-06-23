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
                <div>
                    <span class="badge bg-success me-1">
                        <i class="fas fa-circle me-1"></i>Encendido
                    </span>
                    <span class="badge bg-danger me-1">
                        <i class="fas fa-circle me-1"></i>Apagado
                    </span>
                    <span class="badge bg-warning text-dark">
                        <i class="fas fa-circle me-1"></i>Indeterminado
                    </span>
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
@endsection

@push('scripts')
<script>
    // Configuracion global para JavaScript desde Laravel
    window.fastapiUrl = '{{ config('monitor.fastapi_url') }}';
    window.fastapiKey = '{{ config('monitor.fastapi_key') }}';
</script>
<script src="{{ asset('js/monitor.js') }}"></script>
@endpush
@push('scripts')
<script src="{{ asset('js/monitor.js') }}"></script>
@endpush
