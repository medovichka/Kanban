<?php

declare(strict_types=1);

namespace App\Http;

final class Response
{
    public function __construct(
        public readonly string $content = '',
        public readonly int $status = 200,
        public readonly array $headers = [],
    ) {
    }

    public function withHeader(string $name, string|array $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        return new self($this->content, $this->status, $headers);
    }

    public static function html(string $content, int $status = 200): self
    {
        return new self($content, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(string|array|bool|int|float|null $data, int $status = 200): self
    {
        $payload = is_string($data)
            ? $data
            : (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new self($payload, $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function redirect(string $location, int $status = 303): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public static function text(string $content, int $status = 200): self
    {
        return new self($content, $status, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function withCookie(
        string $name,
        string $value,
        ?int $expires = null,
        string $path = '/',
        bool $httpOnly = true,
        bool $secure = false,
        string $sameSite = 'Lax',
    ): self {
        $cookie = rawurlencode($name) . '=' . rawurlencode($value);

        if ($expires !== null) {
            $cookie .= '; Expires=' . gmdate('D, d M Y H:i:s', $expires) . ' GMT';
            $cookie .= '; Max-Age=' . max(0, $expires - time());
        }

        $cookie .= '; Path=' . $path;

        if ($httpOnly) {
            $cookie .= '; HttpOnly';
        }

        if ($secure) {
            $cookie .= '; Secure';
        }

        if ($sameSite !== '') {
            $cookie .= '; SameSite=' . $sameSite;
        }

        $headers = $this->headers;
        $headers['Set-Cookie'][] = $cookie;

        return new self($this->content, $this->status, $headers);
    }

    public function withoutCookie(string $name, string $path = '/'): self
    {
        return $this->withCookie($name, '', 0, $path);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $values) {
                foreach ((array) $values as $value) {
                    header($name . ': ' . $value, false);
                }
            }
        }

        echo $this->content;
    }
}
