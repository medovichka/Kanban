<?php

declare(strict_types=1);

namespace App\Http;

use App\Container;

abstract class Controller
{
    protected Request $request;

    protected array $parameters = [];

    abstract public function handle(Request $request): Response;

    public function before(Request $request, array $parameters = []): void
    {
        $this->request = $request;
        $this->parameters = $parameters;
    }

    public function after(Request $request, Response $response): ?Response
    {
        return null;
    }

    public function teardown(): void
    {
    }

    public function parameter(string $name, ?string $default = null): ?string
    {
        return $this->parameters[$name] ?? $default;
    }

    public function request(): Request
    {
        return $this->request;
    }

    protected function service(string $class): object
    {
        $container = Container::current() ?? Container::application();

        return $container->get($class);
    }

    protected function logger(): \Monolog\Logger
    {
        $container = Container::current() ?? Container::application();

        return $container->logger();
    }

    protected function view(string $html, int $status = 200): Response
    {
        return Response::html($html, $status);
    }

    protected function json(string|array|bool|int|float|null $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $location, int $status = 303): Response
    {
        return Response::redirect($location, $status);
    }

    protected function notFound(string $message = 'Страница не найдена'): Response
    {
        return $this->error(404, $message);
    }

    protected function error(int $status, string $message): Response
    {
        return Response::html(
            '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8">'
            . '<title>' . $status . '</title></head><body style="font-family: system-ui, sans-serif; max-width: 640px; margin: 3rem auto">'
            . '<h1>' . $status . '</h1><p>' . $this->h($message) . '</p>'
            . '<p><a href="/">На главную</a></p></body></html>',
            $status
        );
    }

    protected function h(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
