<?php
declare(strict_types=1);

// PSR-4 autoloader: App\Foo\Bar -> app/Foo/Bar.php. Vendored libraries register
// their own prefixes here so the server needs no composer.
spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'App\\' => __DIR__ . '/',
        'starfederation\\datastar\\' => dirname(__DIR__) . '/vendor/starfederation/datastar-php/src/',
    ];
    foreach ($prefixes as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});

require_once __DIR__ . '/View/helpers.php';
