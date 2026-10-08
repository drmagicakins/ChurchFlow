<?php

// MERGE this disk into the 'disks' array of your existing
// config/filesystems.php — do not replace that file.
//
// §23/§43: outside the public webroot, never served directly by the web
// server. In production point this at S3/equivalent private bucket
// instead of local storage for real redundancy; local is fine for dev.

return [
    'disks' => [
        'private' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
        ],
    ],
];
