<?php

/**
 * Mechinno — فایل نمونه تنظیمات (Production)
 * برای راه‌اندازی: این فایل را به config.php کپی کنید.
 */

return [
    'app_name' => 'Mechinno Management Panel',
    'timezone' => 'Asia/Tehran',
    'debug' => false,

    'auth' => [
        'enabled' => true,
        // قبل از لانچ این رمزها را عوض کنید. هرگز رمز نمونه را در production نگه ندارید.
        'username' => 'admin',
        'password' => 'CHANGE_ME_ADMIN_PASSWORD',
        'password_hash' => '',
        'viewer_username' => 'viewer',
        'viewer_password' => 'CHANGE_ME_VIEWER_PASSWORD',
        'viewer_password_hash' => '',
    ],

    /*
     * cPanel / MySQL
     * 1. دیتابیس و کاربر MySQL را در cPanel بسازید.
     * 2. این فایل را به config.php کپی کنید.
     * 3. نام دیتابیس، کاربر و رمز واقعی خودتان را وارد کنید.
     */
    'db' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'YOUR_DB_NAME',
        'username' => 'YOUR_DB_USER',
        'password' => 'CHANGE_ME_DB_PASSWORD',
        'charset' => 'utf8mb4',
    ],

    /*
     * توسعه محلی (اختیاری):
     *
     * 'db' => [
     *     'driver' => 'sqlite',
     *     'path' => __DIR__ . '/data/mechinno.sqlite3',
     * ],
     */
];
