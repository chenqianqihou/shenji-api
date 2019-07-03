<?php

return [
    'mysql' => [
        'class' => 'yii\db\Connection',
        'dsn' => 'mysql:host=10.10.10.10;dbname=shenji',
        'username' => 'root',
        'password' => '123456',
        'charset' => 'utf8',
    ],
    'redis' => [
        'class' => 'yii\redis\Connection',
        'hostname' => '10.10.10.10',
        'port' => 6379,
        'database' => 0,
    ],
];
