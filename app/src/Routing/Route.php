<?php

declare(strict_types=1);

namespace App\Routing;

use RuntimeException;

final class Route
{
    public readonly array $methods;

    public readonly array $parameterNames;

    private array $attributes;

    public function __construct(
        string|array $methods,
        public readonly string $pattern,
        public readonly string $controller,
        array $attributes = [],
    ) {
        $methods = is_array($methods) ? $methods : [$methods];
        $this->methods = array_values(array_unique(array_map(static fn (string $m): string => strtoupper($m), $methods)));
        $this->attributes = $attributes;
        $this->parameterNames = self::parseParameterNames($pattern);
    }

    public function regex(): string
    {
        $quoted = preg_quote($this->pattern, '#');

        $regex = (string) preg_replace('#\\\\\{[A-Za-z0-9_]+\\\\\}#', '([^/]+)', $quoted);

        return '#^' . $regex . '$#';
    }

    public function pathMatch(string $path): bool
    {
        return preg_match($this->regex(), $path) === 1;
    }

    public function allows(string $method): bool
    {
        return in_array(strtoupper($method), $this->methods, true);
    }

    public function extractParameters(string $path): array
    {
        if (preg_match($this->regex(), $path, $matches) !== 1) {
            return [];
        }

        $result = [];
        foreach (array_keys($this->parameterNames) as $index => $name) {
            $result[$name] = rawurldecode((string) ($matches[$index + 1] ?? ''));
        }

        return $result;
    }

    public function name(): ?string
    {
        $name = $this->attributes['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    public function attribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    private static function parseParameterNames(string $pattern): array
    {
        preg_match_all('#\{([A-Za-z0-9_]+)\}#', $pattern, $matches);

        $names = [];
        foreach ($matches[1] as $index => $name) {
            if (isset($names[$name])) {
                throw new RuntimeException(sprintf('Route "%s" uses parameter "%s" twice.', $pattern, $name));
            }
            $names[$name] = $index;
        }

        return $names;
    }
}
