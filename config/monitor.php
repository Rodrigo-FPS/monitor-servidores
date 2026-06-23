// Funcion para escapar HTML y prevenir XSS
function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Funcion para calcular tiempo relativo
function timeAgo(dateString) {
    if (!dateString) return 'Nunca';
    
    try {
        var date = new Date(dateString);
        var now = new Date();
        var diffMs = now - date;
        var diffSec = Math.floor(diffMs / 1000);
        var diffMin = Math.floor(diffSec / 60);
        var diffHour = Math.floor(diffMin / 60);
        var diffDay = Math.floor(diffHour / 24);

        if (diffDay > 0) {
            return 'Hace ' + diffDay + ' dia' + (diffDay > 1 ? 's' : '');
        } else if (diffHour > 0) {
            return 'Hace ' + diffHour + ' hora' + (diffHour > 1 ? 's' : '');
        } else if (diffMin > 0) {
            return 'Hace ' + diffMin + ' minuto' + (diffMin > 1 ? 's' : '');
        } else if (diffSec > 10) {
            return 'Hace ' + diffSec + ' segundos';
        } else {
            return 'Hace unos momentos';
        }
    } catch (e) {
        return 'Nunca';
    }
}

// Funcion para renderizar la tabla desde JSON de FastAPI
function renderizarTablaDesdeJson(servers) {
    var html = '';
    
    if (!servers || servers.length === 0) {
        html = '<tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-database me-2"></i>No hay servidores registrados.</td></tr>';
    } else {
        for (var i = 0; i < servers.length; i++) {
            var server = servers[i];
            
            var statusClass = '';
            var statusIcon = '';
            var statusText = '';
            
            if (server.estado === 'encendido') {
                statusClass = 'bg-success';
                statusIcon = 'fa-check-circle';
                statusText = 'Encendido';
            } else if (server.estado === 'apagado') {
                statusClass = 'bg-danger';
                statusIcon = 'fa-power-off';
                statusText = 'Apagado';
            } else {
                statusClass = 'bg-warning text-dark';
                statusIcon = 'fa-question-circle';
                statusText = 'Indeterminado';
            }

            var lastSeen = timeAgo(server.ultimo_visto);

            html += '<tr>';
            html += '<td><strong>' + escapeHtml(server.hostname) + '</strong></td>';
            html += '<td><code>' + escapeHtml(server.ip) + '</code></td>';
            html += '<td><span class="badge ' + statusClass + '"><i class="fas ' + statusIcon + ' me-1"></i>' + statusText + '</span></td>';
            html += '<td>' + lastSeen + '</td>';
            html += '<td><button class="btn btn-sm btn-outline-secondary" disabled><i class="fas fa-eye"></i> Ver</button></td>';
            html += '</tr>';
        }
    }
    
    document.getElementById('servers-table-body').innerHTML = html;
}

// Funcion para actualizar la hora
function actualizarHoraUltimaActualizacion() {
    var now = new Date();
    var hours = String(now.getHours()).padStart(2, '0');
    var minutes = String(now.getMinutes()).padStart(2, '0');
    var seconds = String(now.getSeconds()).padStart(2, '0');
    document.getElementById('last-update-time').textContent = hours + ':' + minutes + ':' + seconds;
}

// Fallback a Laravel
function obtenerDatosDeLaravel() {
    $.ajax({
        url: '/monitor/data',
        type: 'GET',
        success: function(html) {
            document.getElementById('servers-table-body').innerHTML = html;
            actualizarHoraUltimaActualizacion();
        },
        error: function() {
            document.getElementById('servers-table-body').innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3"><i class="fas fa-exclamation-triangle me-2"></i>Error al cargar los datos.</td></tr>';
        }
    });
}

// Funcion principal de actualizacion
function actualizarTabla() {
    var fastapiUrl = window.fastapiUrl || '';
    var fastapiKey = window.fastapiKey || '';

    if (fastapiUrl && fastapiKey) {
        $.ajax({
            url: fastapiUrl + '/api/admin/servidores',
            type: 'GET',
            headers: {
                'X-API-Key': fastapiKey
            },
            timeout: 5000,
            success: function(data) {
                renderizarTablaDesdeJson(data);
                actualizarHoraUltimaActualizacion();
            },
            error: function() {
                obtenerDatosDeLaravel();
            }
        });
    } else {
        obtenerDatosDeLaravel();
    }
}

// Inicializar
$(document).ready(function() {
    actualizarTabla();
    setInterval(actualizarTabla, 10000);
});