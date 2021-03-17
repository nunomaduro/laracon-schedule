<?php

return [
    'default' => 'local',
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => getcwd(),
        ],
        'windowsconfig' => [
            'driver' => 'local',
            'root' => storage_path('../app/Commands/data'),
        ],
    ],
];
