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
        'username' => 'admin',
        'password' => '159357',
        'password_hash' => '',
        'viewer_username' => 'viewer',
        'viewer_password' => '159357',
        'viewer_password_hash' => '',
    ],

    /*
     * cPanel / MySQL
     * 1. دیتابیس و کاربر MySQL را در cPanel بسازید.
     * 2. این فایل را به config.php کپی کنید.
     * 3. در صورت نیاز نام دیتابیس و کاربر را اصلاح کنید.
     */
    'db' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'h325207_inn',
        'username' => 'h325207_inu',
        'password' => '159357aa258654A',
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
