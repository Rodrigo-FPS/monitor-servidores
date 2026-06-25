// Rol del usuario en sesion (publicado en <meta name="es-admin">). Solo controla la
// UI; la autorizacion REAL la aplica el servidor (middleware rol.admin + revalidacion
// en el controlador). Un usuario que forzara la peticion recibe 403.
var ES_ADMIN = document.querySelector('meta[name="es-admin"]')
    && document.querySelector('meta[name="es-admin"]').getAttribute('content') === 'true';

var paginaActual  = 1;
var totalPaginas  = 1;
var POR_PAGINA    = 25;

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
            if (ES_ADMIN) {
                html += '<button class="btn btn-sm btn-outline-primary me-1 btn-editar-servidor" ' +
                        'data-server-id="' + escapeHtml(s.server_id) + '" data-ip="' + escapeHtml(s.ip) + '" ' +
                        'aria-label="Editar ' + escapeHtml(s.hostname) + '">' +
                        '<i class="fas fa-edit" aria-hidden="true"></i></button>';
                html += '<button class="btn btn-sm btn-outline-danger btn-eliminar-servidor" ' +
                        'data-server-id="' + escapeHtml(s.server_id) + '" ' +
                        'aria-label="Eliminar ' + escapeHtml(s.hostname) + '">' +
                        '<i class="fas fa-trash" aria-hidden="true"></i></button>';
            } else {
                html += '<span class="text-muted small">Solo lectura</span>';
            }
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

function renderizarPaginacion(pagina, paginas, total) {
    var contenedor = document.getElementById('paginacion-container');
    if (!contenedor) return;

    if (paginas <= 1) {
        contenedor.innerHTML = '';
        return;
    }

    var label = total + ' servidor' + (total !== 1 ? 'es' : '');
    var html  = '<nav class="d-flex flex-wrap justify-content-between align-items-center mt-2 gap-2" ' +
                'aria-label="Paginación de servidores">' +
                '<small class="text-muted">' + label + ' en total</small>' +
                '<ul class="pagination pagination-sm mb-0">';

    html += '<li class="page-item' + (pagina <= 1 ? ' disabled' : '') + '">' +
            '<button class="page-link" id="btn-pag-ant"' + (pagina <= 1 ? ' disabled aria-disabled="true"' : '') +
            ' aria-label="Página anterior">‹ Anterior</button></li>';

    html += '<li class="page-item disabled" aria-current="page">' +
            '<span class="page-link">' + pagina + ' / ' + paginas + '</span></li>';

    html += '<li class="page-item' + (pagina >= paginas ? ' disabled' : '') + '">' +
            '<button class="page-link" id="btn-pag-sig"' + (pagina >= paginas ? ' disabled aria-disabled="true"' : '') +
            ' aria-label="Página siguiente">Siguiente ›</button></li>';

    html += '</ul></nav>';
    contenedor.innerHTML = html;

    if (pagina > 1) {
        document.getElementById('btn-pag-ant').addEventListener('click', function() {
            paginaActual--;
            actualizarTabla();
        });
    }
    if (pagina < paginas) {
        document.getElementById('btn-pag-sig').addEventListener('click', function() {
            paginaActual++;
            actualizarTabla();
        });
    }
}

function actualizarTabla() {
    $.ajax({
        url: '/api/admin/servidores',
        type: 'GET',
        data: { pagina: paginaActual, por_pagina: POR_PAGINA },
        timeout: 5000,
        success: function(data) {
            if (!data || !Array.isArray(data.servidores)) return;

            // Si la pagina actual ya no existe (ej: se borraron servidores), volver a la ultima
            if (paginaActual > data.paginas && data.paginas > 0) {
                paginaActual = data.paginas;
                actualizarTabla();
                return;
            }

            totalPaginas = data.paginas || 1;
            renderizarTablaDesdeJson(data.servidores);
            renderizarPaginacion(paginaActual, totalPaginas, data.total);
            actualizarHoraUltimaActualizacion();
        },
        error: function() {
            document.getElementById('servers-table-body').innerHTML =
                '<tr><td colspan="5" class="text-center text-danger py-3">' +
                '<i class="fas fa-exclamation-triangle me-2" aria-hidden="true"></i>Error de conexión con el motor de estados.</td></tr>';
        }
    });
}

function modalInstance(id) {
    return bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
}

function mostrarToast(mensaje) {
    var contenedor = document.getElementById('toast-contenedor');
    if (!contenedor) {
        contenedor = document.createElement('div');
        contenedor.id = 'toast-contenedor';
        contenedor.className = 'position-fixed bottom-0 end-0 p-3';
        contenedor.style.zIndex = '1100';
        contenedor.setAttribute('aria-live', 'polite');
        contenedor.setAttribute('aria-atomic', 'true');
        document.body.appendChild(contenedor);
    }
    var id = 'toast-' + Date.now();
    contenedor.insertAdjacentHTML('beforeend',
        '<div id="' + id + '" class="toast align-items-center text-bg-success border-0" role="status">' +
        '<div class="d-flex"><div class="toast-body">' +
        '<i class="fas fa-check-circle me-2" aria-hidden="true"></i>' + escapeHtml(mensaje) +
        '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" ' +
        'data-bs-dismiss="toast" aria-label="Cerrar"></button></div></div>'
    );
    var el = document.getElementById(id);
    var t = new bootstrap.Toast(el, { delay: 3000 });
    t.show();
    el.addEventListener('hidden.bs.toast', function() { el.remove(); });
}

$(document).ready(function() {
    // Enviar el token CSRF en toda peticion AJAX que modifique estado.
    // El token se publica en <meta name="csrf-token"> dentro de layouts/app.blade.php.
    var csrfToken = $('meta[name="csrf-token"]').attr('content');
    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': csrfToken }
    });

    actualizarTabla();
    setInterval(actualizarTabla, 10000);

    // El rol usuario es de solo lectura: no se enlazan los controles de gestion.
    // La autorizacion real esta en el servidor; esto es solo limpieza de UI.
    if (!ES_ADMIN) return;

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
            success:  function()    { modalInstance('modal-editar').hide(); actualizarTabla(); mostrarToast('Servidor actualizado correctamente.'); },
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
            success:  function()    { modalInstance('modal-eliminar').hide(); actualizarTabla(); mostrarToast('Servidor eliminado correctamente.'); },
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
        $('#agregar-clave-publica').val('');
        $('#agregar-error').addClass('d-none').text('');
        $('#btn-confirmar-agregar').prop('disabled', false).html('<i class="fas fa-plus me-1"></i>Agregar');
    });

    // ── Confirmar agregar ─────────────────────────────────────────────────────
    $('#btn-confirmar-agregar').on('click', function() {
        var id        = $('#agregar-server-id').val().trim();
        var hostname  = $('#agregar-hostname').val().trim();
        var ip        = $('#agregar-ip').val().trim();
        var clavePub  = $('#agregar-clave-publica').val().trim();

        if (!id || !hostname || !ip || !clavePub) {
            $('#agregar-error').removeClass('d-none').text('Todos los campos son requeridos.');
            return;
        }
        if (!clavePub.startsWith('-----BEGIN PUBLIC KEY-----')) {
            $('#agregar-error').removeClass('d-none').text('La clave pública debe estar en formato PEM.');
            return;
        }

        var btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Agregando...');
        $.ajax({
            url: '/api/admin/servidores',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ server_id: id, hostname: hostname, ip: ip, clave_publica: clavePub }),
            timeout: 5000,
            success:  function()    { modalInstance('modal-agregar').hide(); actualizarTabla(); mostrarToast('Servidor agregado correctamente.'); },
            error:    function(xhr) {
                var msg = 'Error al agregar el servidor.';
                try { msg = JSON.parse(xhr.responseText).error || msg; } catch(e) {}
                $('#agregar-error').removeClass('d-none').text(msg);
                btn.prop('disabled', false).html('<i class="fas fa-plus me-1"></i>Agregar');
            }
        });
    });
});
