<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CronController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\CronPanelController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\RegisterController;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\VerifyCsrfToken;
use App\Routing\Router;

return static function (Router $router): void {
    $router->add(['GET'], '/', HomeController::class, ['name' => 'home']);
    $router->add(['GET'], '/about', PagesController::class, ['name' => 'about']);
    $router->add(['GET'], '/cron/status', CronPanelController::class, ['name' => 'cron.panel']);

    $router->add(['GET'], '/register', RegisterController::class, ['name' => 'register.form']);

    $router->add(['POST'], '/register', RegisterController::class, [
        'name' => 'register.store',
        'middleware' => [VerifyCsrfToken::class],
    ]);

    $router->add(['GET'], '/login', LoginController::class, ['name' => 'login.form']);
    $router->add(['POST'], '/login', LoginController::class, ['name' => 'login.store']);

    $router->add(['POST'], '/logout', LogoutController::class, [
        'name' => 'logout',
        'middleware' => [VerifyCsrfToken::class],
    ]);

    $router->add(['GET'], '/dashboard', DashboardController::class, [
        'name' => 'dashboard',
        'middleware' => [Authenticate::class],
    ]);
    $router->add(['POST'], '/dashboard', DashboardController::class, [
        'name' => 'dashboard.logout_others',
        'middleware' => [Authenticate::class],
    ]);

    $router->group(['prefix' => '/api', 'name' => 'api.'], static function (Router $router): void {
        $router->add(['GET'], '/status', StatusController::class, ['name' => 'status']);
        $router->add(['GET'], '/cron', CronController::class, ['name' => 'cron']);
    });

    $router->group([
        'prefix' => '/api',
        'middleware' => [Authenticate::class],
        'name' => 'api.',
    ], static function (Router $router): void {
        $router->add(['GET'], '/me', SessionController::class, ['name' => 'me']);
        $router->add(['GET'], '/sessions', SessionController::class, ['name' => 'sessions.index']);
        $router->add(['DELETE'], '/sessions/{id}', SessionController::class, ['name' => 'sessions.destroy']);
        $router->add(['POST'], '/sessions/destroy-all', SessionController::class, ['name' => 'sessions.destroy_all']);
    });
};
