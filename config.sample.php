<?php

return [
    'app_name' => 'Mechinno Management Panel',
    'timezone' => 'Asia/Tehran',

    /*
     * cPanel path:
     * 1. Create a MySQL database and user in cPanel.
     * 2. Copy this file to config.php.
     * 3. Put your database name, username, and password below.
     */
    'db' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'cpaneluser_mechinno',
        'username' => 'cpaneluser_mechinno',
        'password' => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],

    /*
     * Optional local development fallback:
     *
     * 'db' => [
     *     'driver' => 'sqlite',
     *     'path' => __DIR__ . '/data/mechinno.sqlite3',
     * ],
     */
];
