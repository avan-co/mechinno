<?php

declare(strict_types=1);

final class Brand
{
    private const BRAND_DIR = 'assets/brand';

    /** @var array<string, array{light: string, dark: string, class: string}> */
    private const VARIANTS = [
        'panel' => [
            'light' => 'icaut-logo-panel.png',
            'dark' => 'icaut-logo-dark-panel.png',
            'class' => 'brand-mark brand-mark--panel',
        ],
        'hero' => [
            'light' => 'icaut-logo.png',
            'dark' => 'icaut-logo-dark.png',
            'class' => 'brand-mark brand-mark--hero',
        ],
        'report' => [
            'light' => 'icaut-logo-panel.png',
            'dark' => 'icaut-logo-dark-panel.png',
            'class' => 'report-brand-mark',
        ],
        'compact' => [
            'light' => 'icaut-logo-sm.png',
            'dark' => 'icaut-logo-dark-sm.png',
            'class' => 'brand-mark brand-mark--compact',
        ],
    ];

    public static function version(): string
    {
        $base = app_base_path() . '/' . self::BRAND_DIR;
        $files = [
            'icaut-logo-panel.png',
            'icaut-logo-dark-panel.png',
            'icaut-logo.png',
            'icaut-logo-dark.png',
            'favicon.ico',
            'site.webmanifest',
        ];
        $mtime = 0;
        foreach ($files as $file) {
            $path = $base . '/' . $file;
            if (is_file($path)) {
                $mtime = max($mtime, (int) filemtime($path));
            }
        }

        return (string) ($mtime > 0 ? $mtime : time());
    }

    public static function asset(string $file): string
    {
        return app_web_base() . self::BRAND_DIR . '/' . ltrim($file, '/');
    }

    public static function headTags(): string
    {
        $v = self::version();
        $favicon = self::asset('favicon.ico') . '?v=' . $v;
        $png32 = self::asset('favicon-32.png') . '?v=' . $v;
        $png16 = self::asset('favicon-16.png') . '?v=' . $v;
        $apple = self::asset('apple-touch-icon.png') . '?v=' . $v;
        $manifest = self::asset('site.webmanifest') . '?v=' . $v;

        return implode("\n    ", [
            '<link rel="icon" href="' . e($favicon) . '" sizes="any" />',
            '<link rel="icon" type="image/png" sizes="32x32" href="' . e($png32) . '" />',
            '<link rel="icon" type="image/png" sizes="16x16" href="' . e($png16) . '" />',
            '<link rel="apple-touch-icon" href="' . e($apple) . '" />',
            '<link rel="manifest" href="' . e($manifest) . '" />',
            '<meta name="theme-color" content="#101012" media="(prefers-color-scheme: dark)" />',
            '<meta name="theme-color" content="#e3f2ff" media="(prefers-color-scheme: light)" />',
        ]);
    }

    public static function mark(string $variant = 'panel'): string
    {
        $config = self::VARIANTS[$variant] ?? self::VARIANTS['panel'];

        return self::renderMark($config['class'], $config['light'], $config['dark']);
    }

    private static function renderMark(string $class, string $lightFile, string $darkFile): string
    {
        $v = self::version();
        $light = e(self::asset($lightFile) . '?v=' . $v);
        $dark = e(self::asset($darkFile) . '?v=' . $v);
        $alt = e(self::altText());

        return '<span class="' . e($class) . '" aria-hidden="true">'
            . '<img class="brand-logo brand-logo--light" src="' . $light . '" alt="' . $alt . '" decoding="async" />'
            . '<img class="brand-logo brand-logo--dark" src="' . $dark . '" alt="' . $alt . '" decoding="async" />'
            . '</span>';
    }

    public static function altText(): string
    {
        return 'مرکز نوآوری دانشکده مهندسی مکانیک — ICAUT';
    }
}
