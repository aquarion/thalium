<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

if (env("LOG_PREFIX")) {
    $logname = env("LOG_PREFIX") . "-";
} else {
    $logname = "";
}

return [

    'channels' => [
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/' . $logname . 'laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'errors' => [
            'driver' => 'single',
            'path' => storage_path('logs/' . $logname . 'laravel.err.log'),
            'level' => 'warning',
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/' . $logname . 'laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
            'replace_placeholders' => true,
        ],
    ],

];
