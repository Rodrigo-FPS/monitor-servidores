<?php

use Illuminate\Support\Facades\Route;

// /api/heartbeat y /api/shutdown los maneja FastAPI directamente via Nginx
// Las rutas de gestion de servidores estan en web.php para que el guard session funcione
