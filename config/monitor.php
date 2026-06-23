<?php

return [

    // URL del servidor FastAPI
    'fastapi_url' => env('FASTAPI_URL', 'http://127.0.0.1:8000'),

    // Clave para FastAPI
    'fastapi_key' => env('FASTAPI_KEY', ''),

    // Tiempo de actualización automática del monitor
    'auto_refresh_segundos' => env('MONITOR_AUTO_REFRESH', 10),

];