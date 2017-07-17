<?php

return [
    'db' => [
        'default' => [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'user' => 'postgres',
            'password' => '{{db.password}}',
            'dbname' => '{{db.productionDb}}'
        ],
        'phpUnitTest' => [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'user' => 'postgres',
            'password' => '{{db.password}}',
            'dbname' => '{{db.unitTestDb}}'
        ]
    ],
    'axl' => [
        'username' => '{{axl.username}}',
        'password' => '{{axl.password}}',
    ]
];
