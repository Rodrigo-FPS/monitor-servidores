<?php

return [
    'defaults' => [
        'guard' => 'admin',
    ],

    // El guard 'admin' identifica la sesion del panel de administracion. Autentica a
    // todas las cuentas del panel; el nivel de acceso lo determina la columna 'rol'
    // (admin u observador), no el guard.
    'guards' => [
        'admin' => [
            'driver'   => 'session',
            'provider' => 'usuarios',
        ],
    ],

    'providers' => [
        'usuarios' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Usuario::class,
        ],
    ],

    // No hay recuperacion de contrasena (el alta de cuentas es manual por consola),
    // por lo que no se define ningun broker de 'passwords'.
];
