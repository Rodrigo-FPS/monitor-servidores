// Función para actualizar la tabla vía AJAX
function actualizarTabla() {
    $.ajax({
        url: '/monitor/data',
        type: 'GET',
        success: function(data) {
            $('#servers-table-body').html(data);
        }
    });
}

// Actualizar cada 10 segundos
setInterval(actualizarTabla, 10000);

// Actualizar al cargar la página
$(document).ready(function() {
    actualizarTabla();
});