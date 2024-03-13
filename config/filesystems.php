<?php

return [

    'cloud' => env('FILESYSTEM_CLOUD', 's3'),

    'disks' => [
        'libris' => [
            'driver' => 'local',
            'root' => env("DOCKER_PDF_LIBRARY"),
            'url' => env('APP_URL') . '/_libris',
            'visibility' => 'public',
        ],

        'thumbnails' => [
            'driver' => 'local',
            'root' => storage_path('app/thumbnails'),
            'url' => env('APP_URL') . '/_thumbnails',
            'visibility' => 'public',
        ],
    ],

];
