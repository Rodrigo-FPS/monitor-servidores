function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return String(unsafe)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function timeAgo(dateString) {
    if (!dateString) return 'Nunca';
    try {
        var date    = new Date(dateString);
        var diffSec  = Math.floor((new Date() - date) / 1000);
        var diffMin  = Math.floor(diffSec  / 60);
        var diffHour = Math.floor(diffMin  / 60);
        var diffDay  = Math.floor(diffHour / 24);
        if (diffDay  > 0) return 'Hace ' + diffDay  + ' dia'    + (diffDay  > 1 ? 's' : '');
        if (diffHour > 0) return 'Hace ' + diffHour + ' hora'   + (diffHour > 1 ? 's' : '');
        if (diffMin  > 0) return 'Hace ' + diffMin  + ' minuto' + (diffMin  > 1 ? 's' : '');
        if (diffSec  > 10) return 'Hace ' + diffSec + ' segundos';
        return 'Hace unos momentos';
    } catch (e) { return 'Nunca'; }
}

function renderizarTablaDesdeJson(servers) {
    var html = '';

    if (!Array.isArray(servers) || servers.length === 0) {
        html = '<tr><td colspan="5" class="text-center text-muted py-4">' +
               '<i class="fas fa-database me-2"></i>No hay servidores registrados.</td></tr>';
    } else {
        for (var i = 0; i < servers.length; i++) {
            var s = servers[i];
            var statusClass, statusIcon, statusText;

            if (s.estado === 'encendido') {
                statusClass = 'bg-success';           statusIcon = 'fa-check-circle';   statusText = 'Encendido';
            } else if (s.estado === 'apagado') {
                statusClass = 'bg-danger';            statusIcon = 'fa-power-off';      statusText = 'Apagado';
            } else {
                statusClass = 'bg-warning text-dark'; statusIcon = 'fa-question-circle'; statusText = 'Indeterminado';
            }

            html += '<tr>';
            html += '<td><strong>' + escapeHtml(s.hostname) + '</strong></td>';
            html += '<td><code>' + escapeHtml(s.ip) + '</code></td>';
            html += '<td><span class="badge ' + statusClass + '"><i class="fas ' + statusIcon + ' me-1"></i>' + statusText + '</span></td>';
            html += '<td>' + timeAgo(s.ultimo_visto) + '</td>';
            html += '<td>';
            html += '<button class="btn btn-sm btn-outline-primary me-1 btn-editar-servidor" ' +
                    'data-server-id="' + escapeHtml(s.server_id) + '" data-ip="' + escapeHtml(s.ip) + '" title="Editar">' +
                    '<i class="fas fa-edit"></i></button>';
            html += '<button class="btn btn-sm btn-outline-danger btn-eliminar-servidor" ' +
                    'data-server-id="' + escapeHtml(s.server_id) + '" title="Eliminar">' +
                    '<i class="fas fa-trash"></i></button>';
            html += '</td>';
            html += '</tr>';
        }
    }

    document.getElementById('servers-table-body').innerHTML = html;
}

function actualizarHoraUltimaActualizacion() {
    var now = new Date();
    document.getElementById('last-update-time').textContent =
        String(now.getHours()).padStart(2, '0') + ':' +
        String(now.getMinutes()).padStart(2, '0') + ':' +
        String(now.getSeconds()).padStart(2, '0');
}

function actualizarTabla() {
    $.ajax({
        url: '/api/admin/servidores',
        type: 'GET',
        timeout: 5000,
        success: function(data) {
            if (!Array.isArray(data)) return;
            renderizarTablaDesdeJson(data);
            actualizarHoraUltimaActualizacion();
        },
        error: function() {
            document.getElementById('servers-table-body').innerHTML =
                '<tr><td colspan="5" class="text-center text-danger py-3">' +
                '<i class="fas fa-exclamation-triangle me-2"></i>Error de conexión con el motor de estados.</td></tr>';
        }
    });
}

function modalInstance(id) {
    return bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
}

$(document).ready(function() {
    actualizarTabla();
    setInterval(actualizarTabla, 10000);

    // ── Abrir modal editar ────────────────────────────────────────────────────
    $(document).on('click', '.btn-editar-servidor', function() {
        var id = $(this).data('server-id');
        var ip = $(this).data('ip');
        $('#editar-server-id-display').val(id);
        $('#editar-ip').val(ip);
        $('#editar-error').addClass('d-none').text('');
        $('#btn-confirmar-editar').data('server-id', id);
        modalInstance('modal-editar').show();
    });

    // ── Confirmar edición ─────────────────────────────────────────────────────
    $('#btn-confirmar-editar').on('click', function() {
        var id = $(this).data('server-id');
        var ip = $('#editar-ip').val().trim();
        if (!ip) { $('#editar-error').removeClass('d-none').text('La dirección IP es requerida.'); return; }

        var btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
        $.ajax({
            url: '/api/admin/servidores/' + encodeURIComponent(id),
            type: 'PATCH',
            contentType: 'application/json',
            data: JSON.stringify({ ip: ip }),
            timeout: 5000,
            success:  function()    { modalInstance('modal-editar').hide(); actualizarTabla(); },
            error:    function(xhr) {
                var msg = 'Error al actualizar el servidor.';
                try { msg = JSON.parse(xhr.responseText).error || msg; } catch(e) {}
                $('#editar-error').removeClass('d-none').text(msg);
            },
            complete: function() { btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar'); }
        });
    });

    // ── Abrir modal eliminar ──────────────────────────────────────────────────
    $(document).on('click', '.btn-eliminar-servidor', function() {
        var id = $(this).data('server-id');
        $('#eliminar-server-id-display').text(id);
        $('#eliminar-error').addClass('d-none').text('');
        $('#btn-confirmar-eliminar').data('server-id', id);
        modalInstance('modal-eliminar').show();
    });

    // ── Confirmar eliminación ─────────────────────────────────────────────────
    $('#btn-confirmar-eliminar').on('click', function() {
        var id  = $(this).data('server-id');
        var btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Eliminando...');
        $.ajax({
            url: '/api/admin/servidores/' + encodeURIComponent(id),
            type: 'DELETE',
            timeout: 5000,
            success:  function()    { modalInstance('modal-eliminar').hide(); actualizarTabla(); },
            error:    function(xhr) {
                var msg = 'Error al eliminar el servidor.';
                try { msg = JSON.parse(xhr.responseText).error || msg; } catch(e) {}
                $('#eliminar-error').removeClass('d-none').text(msg);
            },
            complete: function() { btn.prop('disabled', false).html('<i class="fas fa-trash me-1"></i>Eliminar'); }
        });
    });

    // ── Limpiar modal agregar al cerrar ───────────────────────────────────────
    $('#modal-agregar').on('hidden.bs.modal', function() {
        $('#agregar-server-id, #agregar-hostname, #agregar-ip').val('');
        $('#agregar-error').addClass('d-none').text('');
    });

    // ── Confirmar agregar ─────────────────────────────────────────────────────
    $('#btn-confirmar-agregar').on('click', function() {
        var id       = $('#agregar-server-id').val().trim();
        var hostname = $('#agregar-hostname').val().trim();
        var ip       = $('#agregar-ip').val().trim();
        if (!id || !hostname || !ip) {
            $('#agregar-error').removeClass('d-none').text('Todos los campos son requeridos.');
            return;
        }

        var btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Agregando...');
        $.ajax({
            url: '/api/admin/servidores',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ server_id: id, hostname: hostname, ip: ip }),
            timeout: 5000,
            success:  function()    { modalInstance('modal-agregar').hide(); actualizarTabla(); },
            error:    function(xhr) {
                var msg = 'Error al agregar el servidor.';
                try { msg = JSON.parse(xhr.responseText).error || msg; } catch(e) {}
                $('#agregar-error').removeClass('d-none').text(msg);
            },
            complete: function() { btn.prop('disabled', false).html('<i class="fas fa-plus me-1"></i>Agregar'); }
        });
    });
});
