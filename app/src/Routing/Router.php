<?php

declare(strict_types=1);

namespace App\Routing;

use App\Container;
use App\Http\Controller as HttpController;
use App\Http\Request;
use App\Http\Response;
use App\Routing\Exceptions\MethodNotAllowedException;
use App\Routing\Exceptions\RouteConfigurationException;
use App\Routing\Exceptions\RouteNotFoundException;

final class Router
{
    private array $routes = [];

    private array $names = [];

    private array $groupStack = [];

    private ?string $matchedName = null;

    public function add(string|array $methods, string $pattern, string $controller, array $attributes = []): Route
    {
        $middleware = $attributes['middleware'] ?? [];
        unset($attributes['middleware']);

        return $this->register($methods, $pattern, $controller, (array) $middleware, $attributes);
    }

    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;

        try {
            $callback($this);
        } finally {
            array_pop($this->groupStack);
        }
    }

    public function matchedRouteName(): ?string
    {
        return $this->matchedName;
    }

    public function routes(): array
    {
        return array_map(static fn (array $item): Route => $item['route'], $this->routes);
    }

    public function match(Request $request): ?MatchResult
    {
        $path = Request::normalizePath($request->path);
        $allowed = [];

        foreach ($this->routes as $item) {
            $route = $item['route'];

            if (!$route->pathMatch($path)) {
                continue;
            }

            if ($route->allows($request->method)) {
                $name = $route->name() ?? self::defaultName($route);
                $this->matchedName = $name;

                return new MatchResult(
                    $route->controller,
                    $route->extractParameters($path),
                    $item['middleware'],
                    $name,
                );
            }

            foreach ($route->methods as $method) {
                $allowed[] = $method;
            }
        }

        if ($allowed !== []) {
            throw new MethodNotAllowedException(array_values(array_unique($allowed)));
        }

        return null;
    }

    public function dispatch(Request $request): Response
    {
        $container = Container::current() ?? Container::application();

        $match = $this->match($request);

        if ($match === null) {
            throw new RouteNotFoundException();
        }

        $controller = $match->createController();
        $controller->before($request, $match->parameters);

        try {
            $response = $match->run($request, $controller, $container);
            $replacement = $controller->after($request, $response);
        } finally {
            $controller->teardown();
        }

        return $replacement ?? $response;
    }

    public function url(string $name, array $parameters = []): string
    {
        if (!isset($this->names[$name])) {
            throw new RouteConfigurationException('Unknown route name "' . $name . '".');
        }

        return (string) preg_replace_callback(
            '#\{([A-Za-z0-9_]+)\}#',
            static function (array $m) use ($parameters, $name): string {
                if (!array_key_exists($m[1], $parameters)) {
                    throw new RouteConfigurationException(sprintf('Route "%s" needs parameter "%s".', $name, $m[1]));
                }

                return rawurlencode((string) $parameters[$m[1]]);
            },
            $this->names[$name]['path']
        );
    }

    private function register(
        string|array $methods,
        string $pattern,
        string $controller,
        array $middleware,
        array $attributes,
    ): Route {
        $prefix = '';
        $groupName = '';

        foreach ($this->groupStack as $group) {
            $prefix .= (string) ($group['prefix'] ?? '');
            $middleware = [...$middleware, ...((array) ($group['middleware'] ?? []))];
            $groupName .= (string) ($group['name'] ?? '');
        }

        $fullPattern = Request::normalizePath($prefix . '/' . ltrim($pattern, '/'));
        $route = new Route($methods, $fullPattern, $controller, $attributes);
        $this->routes[] = ['route' => $route, 'middleware' => $middleware];

        $routeName = $attributes['name'] ?? null;
        if (is_string($routeName) && $routeName !== '') {
            $routeName = $groupName . $routeName;
            if (isset($this->names[$routeName])) {
                throw new RouteConfigurationException('Route name "' . $routeName . '" is already in use.');
            }
            $this->names[$routeName] = ['path' => $fullPattern];
        }

        return $route;
    }

    public static function defaultName(Route $route): string
    {
        $static = [];
        foreach (explode('/', $route->pattern) as $segment) {
            if ($segment !== '' && $segment[0] !== '{') {
                $static[] = $segment;
            }
        }

        return '/' . implode('/', $static);
    }
}
