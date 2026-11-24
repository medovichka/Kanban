<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\Authenticator;
use App\Http\Request;
use App\Http\Response;

final class Authenticate
{
    public static function handle(Request $request, callable $next, Authenticator $auth): Response
    {
        $session = $auth->session($request);
        $isApi = str_starts_with($request->path, '/api');

        if ($session !== null && $auth->user($request) !== null) {
            return $next($request);
        }

        $session?->destroy();

        return $isApi
            ? Response::json(['error' => 'unauthorized'], 401)
            : Response::redirect('/login?next=' . rawurlencode($request->path), 303);
    }
}
