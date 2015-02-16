<?php
use rockunit\migrations\SessionsMigration;

return [
    'databases' => [
        'cubrid' => [
            'dsn' => 'cubrid:dbname=demodb;host=localhost;port=33000',
            'username' => 'dba',
            'password' => '',
            'fixture' => __DIR__ . '/cubrid.sql',
        ],
        'mysql' => [
            'dsn' => 'mysql:host=127.0.0.1;dbname=rocktest',
            'username' => 'root',
            'password' => 'demo',
            'fixture' => __DIR__ . '/mysql.sql',
            'migrations' => [
                SessionsMigration::className(),
            ]
        ],
        'sqlite' => [
            'dsn' => 'sqlite::memory:',
            'fixture' => __DIR__ . '/sqlite.sql',
        ],
        'sqlsrv' => [
            'dsn' => 'sqlsrv:Server=localhost;Database=rocktest',
            'username' => '',
            'password' => '',
            'fixture' => __DIR__ . '/mssql.sql',
        ],
        'pgsql' => [
            'dsn' => 'pgsql:host=localhost;dbname=rocktest;port=5432;',
            'username' => 'rock',
            'password' => 'rock',
            'fixture' => __DIR__ . '/postgres.sql',
            'migrations' => [
                SessionsMigration::className(),
            ]
        ],
    ]
];
