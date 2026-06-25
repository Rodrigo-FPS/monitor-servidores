<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace'   => (bool) env('LOG_DEPRECATIONS_TRACE', false),
    ],

    'channels' => [

        'stack' => [
            'driver'            => 'stack',
            'channels'          => ['daily'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path'   => storage_path('logs/laravel.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
        ],

        'daily' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/laravel.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
            'days'   => env('LOG_DAILY_DAYS', 14),
        ],

        // Auditoria de acciones del administrador (login, alta/edicion/borrado de
        // servidores). Archivo separado y con mayor retencion.
        'auditoria' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/auditoria.log'),
            'level'  => 'info',
            'days'   => env('LOG_AUDIT_DAYS', 90),
        ],

        // Eventos de seguridad: logins fallidos, bloqueos por fuerza bruta y
        // deteccion de secuestro de sesion.
        'seguridad' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/seguridad.log'),
            'level'  => 'info',
            'days'   => env('LOG_SECURITY_DAYS', 90),
        ],

        'stderr' => [
            'driver'    => 'monolog',
            'level'     => env('LOG_LEVEL', 'debug'),
            'handler'   => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with'      => ['stream' => 'php://stderr'],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver'     => 'syslog',
            'level'      => env('LOG_LEVEL', 'debug'),
            'facility'   => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level'  => env('LOG_LEVEL', 'debug'),
        ],

        'null' => [
            'driver'  => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
