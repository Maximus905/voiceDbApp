<?php

return [
    'db' => [
        'default' => [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'user' => 'postgres',
            'password' => '{{db.password}}',
            'dbname' => 'phpVDB'
        ],
        'phpUnitTest' => [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'user' => 'postgres',
            'password' => '{{db.password}}',
            'dbname' => 'phpUnitTest'
        ]
    ]
];