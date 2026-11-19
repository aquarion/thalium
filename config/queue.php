<?php

// Everything else comes from the framework's defaults; only the redis connection is overridden.
return [

    'connections' => [

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            // Library scans can run for many minutes (Horizon allows each job 1800s, see
            // config/horizon.php). A job still running when retry_after passes is handed to a
            // second worker, so this has to exceed that timeout. The framework default is 90.
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 1900),
            'block_for' => null,
            'after_commit' => false,
        ],

    ],

];
