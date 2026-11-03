<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$php = PHP_BINARY;

$scenarios = [
    'Routing: параметры, 405, 404, url()' => <<<'PHP'
        require $base . '/src/Routing/Exceptions/RouteConfigurationException.php';
        require $base . '/src/Routing/Exceptions/RouteNotFoundException.php';
        require $base . '/src/Routing/Exceptions/MethodNotAllowedException.php';
        require $base . '/src/Http/Response.php';
        require $base . '/src/Http/Request.php';
        require $base . '/src/Container.php';
        require $base . '/src/Config.php';
        require $base . '/src/Http/Controller.php';
        require $base . '/src/Routing/Route.php';
        require $base . '/src/Routing/MatchResult.php';
        require $base . '/src/Routing/Router.php';

        $router = new App\Routing\Router();
        $router->add(['GET'], '/', SmokeController::class, ['name' => 'home']);
        $router->add(['DELETE'], '/cards/{cardId}', SmokeController::class, ['name' => 'card.delete']);

        assert($router->url('card.delete', ['cardId' => 42]) === '/cards/42', 'url()');

        $match = $router->match(new App\Http\Request('DELETE', '/cards/42/'));
        assert($match !== null && $match->parameters === ['cardId' => '42'], 'параметры маршрута');
        assert($match->name === 'card.delete', 'имя маршрута');

        try {
            $router->match(new App\Http\Request('POST', '/cards/42'));
            throw new LogicException('ожидали 405');
        } catch (App\Routing\Exceptions\MethodNotAllowedException $e) {
            assert($e->allowed === ['DELETE'], '405 + Allow');
        }

        assert($router->match(new App\Http\Request('GET', '/nope')) === null, '404');
        assert(App\Http\Request::normalizePath('//a///b/') === '/a/b', 'нормализация пути');
        print("router ok\n");
        PHP,

    'Cron: выражение, диапазоны, nextRun()' => <<<'PHP'
        require $base . '/src/Cron/CronExpression.php';

        $e = new App\Cron\CronExpression('*/5 * * * *');
        assert($e->isDue(new DateTimeImmutable('2026-01-01 12:05:00')), '*/5 попадает');
        assert(!$e->isDue(new DateTimeImmutable('2026-01-01 12:06:00')), '*/5 не попадает');

        $e = new App\Cron\CronExpression('0 3 * * 1-5');
        assert($e->isDue(new DateTimeImmutable('2026-01-05 03:00:00')), 'будний день в 3:00');
        assert(!$e->isDue(new DateTimeImmutable('2026-01-04 03:00:00')), 'выходной');
        assert($e->nextRun(new DateTimeImmutable('2026-01-03 10:00:00'))->format('Y-m-d H:i') === '2026-01-05 03:00', 'nextRun');

        assert(count((new App\Cron\CronExpression('0 0 1,15 * *'))->values('dayOfMonth')) === 2, 'список');
        print("cron ok\n");
        PHP,

    'Http: Response, cookie, redirect' => <<<'PHP'
        require $base . '/src/Http/Response.php';
        require $base . '/src/Http/Request.php';

        $r = App\Http\Response::json(['ok' => true]);
        assert($r->headers['Content-Type'] === 'application/json; charset=utf-8', 'json content type');

        $r = App\Http\Response::redirect('/login')->withCookie('kanban_session', 'abc', time() + 100);
        assert($r->headers['Location'] === '/login', 'redirect');
        assert(str_contains((string) $r->headers['Set-Cookie'][0], 'HttpOnly'), 'cookie httpOnly');

        $request = new App\Http\Request('GET', '/x', 'example.test', [], [], ['kanban_session' => 'abc']);
        assert($request->cookie('kanban_session') === 'abc', 'cookie request');
        print("http ok\n");
        PHP,
];

$failed = 0;

foreach ($scenarios as $label => $code) {
    $file = tempnam(sys_get_temp_dir(), 'smoke_') . '.php';
    file_put_contents($file, "<?php\n\$base = " . var_export($base, true) . ";\n" . $code);

    $output = [];
    exec(escapeshellarg($php) . ' ' . escapeshellarg($file) . ' 2>&1', $output, $status);
    @unlink($file);

    if ($status === 0) {
        fwrite(STDOUT, 'OK   ' . $label . "\n");
        continue;
    }

    $failed++;
    fwrite(STDOUT, 'FAIL ' . $label . "\n    " . implode("\n    ", $output) . "\n");
}

exit($failed === 0 ? 0 : 1);
