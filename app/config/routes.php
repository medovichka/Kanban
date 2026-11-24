<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CronController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\CronPanelController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FrontendController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PagesController;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\VerifyCsrfToken;
use App\Routing\Router;

return static function (Router $router): void {
    $router->add(['GET'], '/', HomeController::class, ['name' => 'home']);
    $router->add(['GET'], '/about', PagesController::class, ['name' => 'about']);
    $router->add(['GET'], '/cron/status', CronPanelController::class, ['name' => 'cron.panel']);

    $router->add(['GET'], '/register', FrontendController::class, [
        'name' => 'register.form',
        'action' => 'register',
    ]);

    $router->add(['POST'], '/register', FrontendController::class, [
        'name' => 'register.store',
        'action' => 'register',
        'middleware' => [VerifyCsrfToken::class],
    ]);

    $router->add(['GET'], '/login', FrontendController::class, [
        'name' => 'login.form',
        'action' => 'login',
    ]);
    $router->add(['POST'], '/login', FrontendController::class, [
        'name' => 'login.store',
        'action' => 'login',
        'middleware' => [VerifyCsrfToken::class],
    ]);

    $router->add(['POST'], '/logout', FrontendController::class, [
        'name' => 'logout',
        'action' => 'logout',
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
