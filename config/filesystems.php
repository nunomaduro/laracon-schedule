<?php

return [
    'default' => 'local',
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => getcwd(),
        ],
        'data' => [
            'driver' => 'local',
            'root' => storage_path('../resources/data'),
        ],
    ],
];
