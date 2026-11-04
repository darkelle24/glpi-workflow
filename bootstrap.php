<?php
declare(strict_types=1);
// SPDX-License-Identifier: GPL-3.0-or-later
spl_autoload_register(static function (string $class): void {
    $prefix = 'WorkflowBuilder\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/src/' . substr($class, strlen($prefix)) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});
