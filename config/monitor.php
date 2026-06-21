<?php

return [
    'login_max_intentos' => (int) env('LOGIN_MAX_INTENTOS', 5),
    'login_ventana_min'  => (int) env('LOGIN_VENTANA_MINUTOS', 10), //ventana en minutos para contar intentos fallidos por IP
];
