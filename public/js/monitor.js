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

// Funcion para calcular tiempo relativo (hace X tiempo)
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

// Funcion para renderizar la tabla a partir de datos JSON (desde FastAPI)
function renderizarTablaDesdeJson(servers) {
    var html = '';
    
    if (!servers || servers.length === 0) {
        html = '<tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-database me-2"></i>No hay servidores registrados.</td></tr>';
    } else {
        for (var i = 0; i < servers.length; i++) {
            var server = servers[i];
            
            // Determinar estado y clase CSS
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

            // Calcular tiempo relativo
            var lastSeen = timeAgo(server.ultimo_visto);

            // Construir fila de la tabla con escape de HTML
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

// Funcion para actualizar la hora de ultima actualizacion
function actualizarHoraUltimaActualizacion() {
    var now = new Date();
    var hours = String(now.getHours()).padStart(2, '0');
    var minutes = String(now.getMinutes()).padStart(2, '0');
    var seconds = String(now.getSeconds()).padStart(2, '0');
    var timeString = hours + ':' + minutes + ':' + seconds;
    document.getElementById('last-update-time').textContent = timeString;
}

// Funcion para obtener datos desde Laravel (fallback)
function obtenerDatosDeLaravel() {
    console.log('Usando Laravel como fallback...');
    
    $.ajax({
        url: '/monitor/data',
        type: 'GET',
        success: function(html) {
            console.log('Datos obtenidos de Laravel (fallback).');
            document.getElementById('servers-table-body').innerHTML = html;
            actualizarHoraUltimaActualizacion();
        },
        error: function(xhr, status, error) {
            console.error('Error al obtener datos de Laravel:', error);
            document.getElementById('servers-table-body').innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3"><i class="fas fa-exclamation-triangle me-2"></i>Error al cargar los datos de los servidores.</td></tr>';
        }
    });
}

// Funcion principal para actualizar la tabla via AJAX
function actualizarTabla() {
    console.log('Actualizando tabla de servidores...');
    
    var fastapiUrl = window.fastapiUrl || '';
    var fastapiKey = window.fastapiKey || '';

    // Intentar obtener datos directamente de FastAPI
    if (fastapiUrl && fastapiKey) {
        $.ajax({
            url: fastapiUrl + '/api/admin/servidores',
            type: 'GET',
            headers: {
                'X-API-Key': fastapiKey
            },
            timeout: 5000,
            success: function(data) {
                console.log('Datos obtenidos de FastAPI:', data.length, 'servidores');
                renderizarTablaDesdeJson(data);
                actualizarHoraUltimaActualizacion();
            },
            error: function(xhr, status, error) {
                console.warn('Error al obtener datos de FastAPI:', error);
                obtenerDatosDeLaravel();
            }
        });
    } else {
        console.warn('FastAPI no configurado, usando Laravel.');
        obtenerDatosDeLaravel();
    }
}

// Inicializar cuando el DOM este listo
$(document).ready(function() {
    console.log('Monitor de Servidores iniciado');
    console.log('Intervalo de actualizacion: 10 segundos');
    
    // Mostrar estado de configuracion
    if (window.fastapiUrl && window.fastapiKey) {
        console.log('FastAPI configurado en:', window.fastapiUrl);
    } else {
        console.warn('FastAPI NO configurado - usando datos de ejemplo');
    }
    
    // Actualizar inmediatamente al cargar la pagina
    actualizarTabla();
    
    // Configurar el intervalo de actualizacion (10 segundos)
    setInterval(actualizarTabla, 10000);
});