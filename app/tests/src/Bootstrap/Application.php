<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Config;
use App\Container;
use App\Database\Connection;
use Monolog\Logger;
use RuntimeException;

final class Application
{
    private static ?Container $container = null;

    public static function basePath(string $path = ''): string
    {
        $base = dirname(__DIR__, 2);

        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }

    public static function current(): ?Container
    {
        return self::$container;
    }

    public static function boot(?array $configOverrides = null): Container
    {
        if (self::$container !== null) {
            return self::$container;
        }

        $autoload = self::basePath('vendor/autoload.php');

        if (!is_file($autoload)) {
            throw new RuntimeException('Не найден vendor/autoload.php. Выполните composer install в каталоге app/.');
        }

        require_once $autoload;

        Config::load([
            'path' => self::basePath('config/config.php'),
            'overrides' => $configOverrides ?? [],
        ]);

        $container = Container::application();
        $logger = $container->logger();

        try {
            Connection::boot((array) Config::get('db', []));
        } catch (\Throwable $e) {
            $logger->error('Boot: database недоступна', ['exception' => $e]);
        }

        $container->get(\App\Session\SessionDriver::class);

        register_shutdown_function(static function () use ($logger): void {
            try {
                $logger->close();
            } catch (\Throwable) {
            }
        });

        return self::$container = $container;
    }
}
