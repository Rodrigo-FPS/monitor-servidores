@extends('layouts.app')

@section('title', 'Monitor de Servidores')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">Estado de Servidores</h3>
                    <div>
                        <span class="badge bg-success">Encendido</span>
                        <span class="badge bg-danger">Apagado</span>
                        <span class="badge bg-warning text-dark">Indeterminado</span>
                    </div>
                </div>
                <div class="card-body">
                    <div id="servers-table-container">
                        @include('monitor._servers_table')
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Variables globales para JavaScript (toma los valores del .env)
    window.fastapiUrl = '{{ env('FASTAPI_URL') }}';
    window.fastapiKey = '{{ env('FASTAPI_KEY') }}';
</script>
<script src="{{ asset('js/monitor.js') }}"></script>
@endpush