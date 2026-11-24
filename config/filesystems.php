<?php

return [

    'cloud' => env('FILESYSTEM_CLOUD', 's3'),

    'disks' => [
        'libris' => [
            'driver' => 'local',
            'root' => env('DOCKER_PDF_LIBRARY'),
            'url' => env('APP_URL').'/_libris',
            'visibility' => 'public',
        ],

        'maps' => [
            'driver' => 'local',
            'root' => env('MAPS_LIBRARY'),
            'url' => env('APP_URL').'/_maps',
            'visibility' => 'public',
        ],

        'thumbnails' => [
            'driver' => 'local',
            'root' => storage_path('app/thumbnails'),
            'url' => env('APP_URL').'/_thumbnails',
            'visibility' => 'public',
            // nginx reads these through the host `thalium` group, not as "other",
            // so keep them out of world-readable. New files inherit the group from
            // the setgid thumbnails directory (set by the deploy role).
            'permissions' => [
                'file' => ['public' => 0640, 'private' => 0600],
                'dir' => ['public' => 02750, 'private' => 0700],
            ],
        ],
    ],

];
