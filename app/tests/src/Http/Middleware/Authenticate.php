<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Models\User;
use App\Session\SessionManager;

final class Authenticate
{
    public static function handle(Request $request, callable $next, SessionManager $sessions): Response
    {
        $id = (string) $request->cookie($sessions->cookieName(), '');
        $session = $id !== '' ? $sessions->find($id) : null;

        $isApi = str_starts_with($request->path, '/api');

        if ($session === null) {
            return $isApi
                ? Response::json(['error' => 'unauthorized'], 401)
                : Response::redirect('/login?next=' . rawurlencode($request->path), 303);
        }

        if ($session->userId <= 0 || User::query()->whereKey($session->userId)->doesntExist()) {
            $session->destroy();

            return $isApi
                ? Response::json(['error' => 'unauthorized'], 401)
                : Response::redirect('/login?next=' . rawurlencode($request->path), 303);
        }

        return $next($request);
    }
}
