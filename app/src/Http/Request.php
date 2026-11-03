<?php

declare(strict_types=1);

namespace App\Http;

use InvalidArgumentException;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $host,
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $cookies = [],
        public readonly array $headers = [],
        public readonly string $clientIp = '',
        public readonly mixed $resolver = null,
    ) {
        if (!str_starts_with($path, '/')) {
            throw new InvalidArgumentException('Request path must start with "/".');
        }
    }

    public static function fromGlobals(array $server, array $get, array $post, array $cookies, ?callable $resolver = null): self
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
            } elseif ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                $name = strtolower(str_replace('_', '-', $key));
            } else {
                continue;
            }
            $headers[$name][] = $value;
        }

        $uri = (string) ($server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        return new self(
            strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET')),
            self::normalizePath($path),
            (string) ($server['HTTP_HOST'] ?? 'localhost'),
            self::stringMap($get),
            self::stringMap($post),
            self::stringMap($cookies),
            $headers,
            (string) ($server['REMOTE_ADDR'] ?? ''),
            $resolver,
        );
    }

    public static function normalizePath(string $path): string
    {
        $path = rawurldecode($path);
        $path = (string) preg_replace('#/+#', '/', $path);

        if ($path === '') {
            return '/';
        }

        if (strlen($path) > 1) {
            $path = rtrim($path, '/');
        }

        return $path === '' ? '/' : $path;
    }

    public function query(string $key, string|array|null $default = null): string|array|null
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, string $default = ''): string
    {
        return $this->body[$key] ?? $default;
    }

    public function cookie(string $name, ?string $default = null): ?string
    {
        return $this->cookies[$name] ?? $default;
    }

    public function header(string $name): ?string
    {
        $values = $this->headers[strtolower($name)] ?? null;

        return $values[0] ?? null;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    private static function stringMap(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $result[$key] = (string) $value;
            }
        }

        return $result;
    }
}
