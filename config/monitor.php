<?php

return [
    'heartbeat_intervalo' => (int) env('HEARTBEAT_INTERVALO_SEGUNDOS', 30), //segundos esperados entre latidos del cliente
    'heartbeat_timeout'   => (int) env('HEARTBEAT_TIMEOUT_SEGUNDOS', 90),   //sin latido por este tiempo el servidor pasa a indeterminado
    'hmac_ventana'        => (int) env('VENTANA_HMAC_SEGUNDOS', 60),         //diferencia maxima aceptada entre timestamp del mensaje y hora actual
    'login_max_intentos'  => (int) env('LOGIN_MAX_INTENTOS', 5),
    'login_ventana_min'   => (int) env('LOGIN_VENTANA_MINUTOS', 10),
];
