<?php

// Para quem não usa Composer: require 'caminho/para/sdks/php/src/autoload.php';
spl_autoload_register(static function (string $class): void {
    $prefix = 'Sharpfy\\NextApi\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
