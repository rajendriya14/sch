<?php

/**
 * PSR-4-like Autoloader — Workforce Automation Scheduling System
 * Maps namespaces: Config\, Controllers\, Models\, Services\, Helpers\
 */
spl_autoload_register(function (string $class): void {
    $prefixes = [
        'Config\\'      => __DIR__ . '/../config/',
        'Controllers\\' => __DIR__ . '/../controllers/',
        'Models\\'      => __DIR__ . '/../models/',
        'Services\\'    => __DIR__ . '/../services/',
        'Helpers\\'     => __DIR__ . '/../helpers/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});
