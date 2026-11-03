<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Bootstrap\Application;
use App\Container;
use App\Http\Request;
use App\Http\Response;
use App\Routing\Exceptions\MethodNotAllowedException;
use App\Routing\Exceptions\RouteNotFoundException;
use App\Routing\Router;

$instrumentation = require __DIR__ . '/../instrumentation/instrument.php';

$route = 'unknown';
$start = hrtime(true);
$container = null;
$response = null;

try {
    $container = Application::boot();

    if (isset($instrumentation['register'])) {
        ($instrumentation['register'])();
    }

    $request = Request::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE);

    $router = (static function (): Router {
        $router = new Router();
        $routes = require __DIR__ . '/../config/routes.php';
        $routes($router);

        return $router;
    })();

    $response = $router->dispatch($request);
    $route = $router->matchedRouteName() ?? 'unknown';
} catch (MethodNotAllowedException $e) {
    $route = 'method_not_allowed';
    $response = Response::html('<h1>405</h1><p>Метод не поддерживается.</p>', 405)
        ->withHeader('Allow', implode(', ', $e->allowed));
} catch (RouteNotFoundException $e) {
    $route = 'not_found';
    $response = Response::html('<h1>404</h1><p>Страница не найдена.</p>', 404);
} catch (\Throwable $e) {
    $route = 'error';
    $response = Response::html('<h1>500</h1><p>Внутренняя ошибка сервера.</p>', 500);

    try {
        ($container instanceof Container ? $container : Container::application())
            ->logger()
            ->error('Unhandled exception', ['exception' => $e, 'route' => $route]);
    } catch (\Throwable $logError) {
        error_log('index: ' . $e->getMessage() . ' | logger: ' . $logError->getMessage());
    }
}

if (isset($instrumentation['finish'])) {
    ($instrumentation['finish'])($route, (hrtime(true) - $start) / 1e9);
}

$response->send();
