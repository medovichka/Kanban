<?php

declare(strict_types=1);

namespace App;

use App\Session\ArraySessionDriver;
use App\Session\DatabaseSessionDriver;
use App\Session\SessionDriver;
use App\Session\SessionManager;
use Closure;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

final class Container
{
    private array $instances = [];

    private array $bindings = [];

    private array $factories = [];

    private ?Logger $logger = null;

    public function __construct(private readonly string $channel = 'app')
    {
    }

    public function bind(string $abstract, string|Closure $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function instance(string $abstract, object $object): void
    {
        $this->instances[$abstract] = $object;
    }

    public function get(string $abstract): object
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $binding = $this->bindings[$abstract] ?? $abstract;
        $object = $binding instanceof Closure ? $binding($this) : $this->build($binding);

        if (!$object instanceof $abstract) {
            throw new RuntimeException(sprintf('Container: %s does not implement %s.', get_debug_type($object), $abstract));
        }

        return $this->instances[$abstract] = $object;
    }

    public function logger(): Logger
    {
        return $this->logger ??= $this->build(Logger::class);
    }

    public static function dependenciesFor(string $class, Container $container): array
    {
        $parameters = (new \ReflectionMethod($class, 'handle'))->getParameters();
        $dependencies = [];

        foreach (array_slice($parameters, 2) as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $container->get($type->getName());
                continue;
            }

            $dependencies[] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
        }

        return $dependencies;
    }

    private function build(string $class): object
    {
        if ($class === Logger::class) {
            return $this->buildLogger();
        }

        $reflection = new \ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException('Container: class is not instantiable: ' . $class);
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $arguments[] = $this->resolveParameter($class, $parameter);
        }

        return $reflection->newInstanceArgs($arguments);
    }

    private function resolveParameter(string $class, ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $this->get($type->getName());
        }

        $value = Config::get('container.' . $parameter->getName());

        if ($value !== null) {
            return $value;
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new RuntimeException(sprintf('Container: cannot resolve $%s of %s.', $parameter->getName(), $class));
    }

    private function buildLogger(): Logger
    {
        $logger = new Logger((string) Config::get('logging.name', $this->channel));
        $logger->pushHandler(new StreamHandler(
            (string) Config::get('logging.path'),
            Logger::toMonologLevel((string) Config::get('logging.level', 'debug'))
        ));

        return $logger;
    }

    private static ?self $current = null;

    public static function setCurrent(?self $container): void
    {
        self::$current = $container;
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    public static function application(?Logger $logger = null): self
    {
        $container = new self();

        if ($logger !== null) {
            $container->instance(Logger::class, $logger);
        }

        $container->bind(SessionDriver::class, static function (): SessionDriver {
            $driver = (string) Config::get('session.driver', 'database');
            $lifetime = (int) Config::get('session.lifetime', 86400);

            return match ($driver) {
                'database' => new DatabaseSessionDriver($lifetime),
                'array' => new ArraySessionDriver(),
                default => throw new RuntimeException('Unknown session driver "' . $driver . '".'),
            };
        });

        $container->bind(
            SessionManager::class,
            static fn (self $c): SessionManager => new SessionManager(
                $c->get(SessionDriver::class),
                (int) Config::get('session.lifetime', 86400),
                (string) Config::get('session.cookie', SessionManager::COOKIE),
            )
        );

        $factories = Config::get('cron.factories', []);
        if (is_array($factories)) {
            foreach ($factories as $class => $factory) {
                if (is_string($class) && $factory instanceof Closure) {
                    $container->bind($class, $factory);
                }
            }
        }

        $container->instance(self::class, $container);

        return self::$current = $container;
    }
}
