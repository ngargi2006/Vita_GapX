<?php

declare(strict_types=1);

/**
 * PSR-4 Autoloader for Vita-Gap
 */
spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'VitaGap\\Tests\\' => __DIR__ . '/../tests/',
        'VitaGap\\' => __DIR__ . '/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
