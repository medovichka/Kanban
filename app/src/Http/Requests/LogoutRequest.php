<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Auth\Authenticator;
use App\Http\Request;
use App\Mvc\Request\AbstractRequest;
use App\Mvc\Response\RedirectResponse;
use App\Mvc\Response\ResponseModel;

final class LogoutRequest extends AbstractRequest
{
    public function __construct(Request $request, private readonly Authenticator $auth)
    {
        parent::__construct($request);
    }

    protected function parameters(): array
    {
        return [];
    }

    public function execute(): ResponseModel
    {
        $this->auth->logout($this->request);

        return (new RedirectResponse('/'))->withoutSession();
    }
}
