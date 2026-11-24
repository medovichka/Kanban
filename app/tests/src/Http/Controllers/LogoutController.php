<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controller;
use App\Http\InteractsWithSession;
use App\Http\Request;
use App\Http\Response;

final class LogoutController extends Controller
{
    use InteractsWithSession;

    public function handle(Request $request): Response
    {
        $this->session()?->destroy();

        return $this->detachSession(Response::redirect('/'));
    }
}
