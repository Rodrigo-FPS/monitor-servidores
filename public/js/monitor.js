// Función para actualizar la tabla vía AJAX
function actualizarTabla() {
    // Usar la URL de FastAPI desde las variables globales
    const fastapiUrl = window.fastapiUrl || '/monitor/data';
    const fastapiKey = window.fastapiKey || '';

    $.ajax({
        url: fastapiUrl + '/servers/status',
        type: 'GET',
        headers: {
            'X-API-Key': fastapiKey
        },
        success: function(data) {
            // Si los datos vienen como JSON (desde FastAPI)
            if (Array.isArray(data)) {
                renderizarTabla(data);
            } else {
                // Si vienen como HTML (desde Laravel)
                $('#servers-table-body').html(data);
            }
        },
        error: function() {
            // Fallback a Laravel si FastAPI no responde
            $.ajax({
                url: '/monitor/data',
                type: 'GET',
                success: function(data) {
                    $('#servers-table-body').html(data);
                }
            });
        }
    });
}

// Función para renderizar la tabla con datos JSON (desde FastAPI)
function renderizarTabla(servers) {
    let html = '';
    if (servers.length === 0) {
        html = '<tr><td colspan="5" class="text-center">No hay servidores registrados.</td></tr>';
    } else {
        servers.forEach(function(server) {
            let statusClass = '';
            let statusText = '';
            if (server.status === 'encendido') {
                statusClass = 'bg-success';
                statusText = 'Encendido';
            } else if (server.status === 'apagado') {
                statusClass = 'bg-danger';
                statusText = 'Apagado';
            } else {
                statusClass = 'bg-warning text-dark';
                statusText = 'Indeterminado';
            }

            html += `
                <tr>
                    <td><strong>${server.hostname}</strong></td>
                    <td><code>${server.ip_address}</code></td>
                    <td><span class="badge ${statusClass}">${statusText}</span></td>
                    <td>${server.last_heartbeat_at || 'Nunca'}</td>
                    <td><button class="btn btn-sm btn-outline-info">Ver</button></td>
                </tr>
            `;
        });
    }
    $('#servers-table-body').html(html);
}

// Actualizar cada 10 segundos
setInterval(actualizarTabla, 10000);

// Actualizar al cargar la página
$(document).ready(function() {
    actualizarTabla();
});