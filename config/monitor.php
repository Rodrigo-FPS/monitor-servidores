<?php

return [
    'login_max_intentos' => (int) env('LOGIN_MAX_INTENTOS', 5),
    'login_ventana_min'  => (int) env('LOGIN_VENTANA_MINUTOS', 10), //ventana en minutos para contar intentos fallidos por IP

    //URL y clave del servidor FastAPI que gestiona la BD de servidores
    'fastapi_url' => (string) env('FASTAPI_URL', 'http://127.0.0.1:8000'),
    'fastapi_key' => (string) env('FASTAPI_KEY', ''),
];
