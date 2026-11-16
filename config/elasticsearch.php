<?php

use Monolog\Level;

return [

    'defaultConnection' => 'default',

    'connections' => [

        'default' => [
            'hosts' => [
                [
                    'host' => env('ELASTICSEARCH_HOST', 'localhost'),
                    'port' => env('ELASTICSEARCH_PORT', 9200),
                    'scheme' => env('ELASTICSEARCH_SCHEME', null),
                    'user' => env('ELASTICSEARCH_USER', null),
                    'pass' => env('ELASTICSEARCH_PASS', null),
                ],
            ],

            'sslVerification' => env('ELASTICSEARCH_CACERT'),

            // A Logger instance can't live here: config:cache var_export()s this array.
            'logPath' => storage_path('logs/elasticsearch.log'),
            'logLevel' => Level::Info,
        ],
    ],
];
