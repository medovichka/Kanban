<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Auth\Authenticator;
use App\Http\Request;
use App\Mvc\Request\AbstractRequest;
use App\Mvc\Request\Parameters\EmailParameter;
use App\Mvc\Request\Parameters\PasswordParameter;
use App\Mvc\Request\Parameters\StringParameter;
use App\Mvc\Response\RedirectResponse;
use App\Mvc\Response\ResponseModel;

final class RegisterRequest extends AbstractRequest
{
    public function __construct(Request $request, private readonly Authenticator $auth)
    {
        parent::__construct($request);
    }

    protected function parameters(): array
    {
        return [
            new StringParameter('name', 'Имя', 1, 100),
            new EmailParameter('email', 'Email', 1, 150),
            new PasswordParameter('password', 'Пароль'),
        ];
    }

    public function execute(): ResponseModel
    {
        $result = $this->auth->register(
            $this->request,
            (string) $this->value('name'),
            (string) $this->value('email'),
            (string) $this->value('password'),
        );

        if ($result->success && $result->session !== null) {
            return (new RedirectResponse('/dashboard'))->withSession($result->session);
        }

        $session = $this->auth->sessionOrStart($this->request)
            ->flash('errors', $result->errors)
            ->flash('old', [
                'name' => (string) $this->value('name'),
                'email' => (string) $this->value('email'),
            ]);

        return (new RedirectResponse('/register'))->withSession($session);
    }
}
