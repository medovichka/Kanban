<?php

declare(strict_types=1);

namespace App\Routing;

use App\Container;
use App\Http\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Routing\Exceptions\RouteConfigurationException;
use Closure;

final class MatchResult
{
    public function __construct(
        public readonly string $controller,
        public readonly array $parameters = [],
        public readonly array $middleware = [],
        public readonly string $name = '',
    ) {
    }

    public function createController(): Controller
    {
        $class = $this->controller;

        if (!class_exists($class) || !is_subclass_of($class, Controller::class)) {
            throw new RouteConfigurationException('Controller must be a class extending ' . Controller::class . ': ' . $class);
        }

        $controller = new $class();

        return $controller;
    }

    public function run(Request $request, Controller $controller, Container $container): Response
    {
        return self::pipeline(
            $this->middleware,
            static fn (Request $request): Response => $controller->handle($request),
            $container,
        )($request);
    }

    public static function pipeline(array $middleware, callable $core, ?Container $container = null): callable
    {
        if ($middleware === []) {
            return $core;
        }

        $next = $core;
        foreach (array_reverse($middleware) as $layer) {
            $prev = $next;
            $callable = is_string($layer) ? self::resolveMiddleware($layer, $container) : $layer;

            if (!is_callable($callable)) {
                throw new RouteConfigurationException('Middleware должна быть замыканием или классом с handle().');
            }

            $next = static fn (Request $request): Response => $callable($request, $prev);
        }

        return $next;
    }

    private static function resolveMiddleware(string $layer, ?Container $container): Closure
    {
        if (!class_exists($layer) || !method_exists($layer, 'handle')) {
            throw new RouteConfigurationException('Класс middleware «' . $layer . '» не найден или не имеет handle().');
        }

        $container ??= Container::current() ?? Container::application();

        return static fn (Request $request, callable $next): Response => $layer::handle(
            $request,
            $next,
            ...Container::dependenciesFor($layer, $container),
        );
    }
}
