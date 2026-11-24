<?php

declare(strict_types=1);

namespace App\Mvc\Response;

use App\Http\Response;
use App\Session\Session;

abstract class ResponseModel
{
    private ?Session $session = null;

    private bool $forgetSession = false;

    abstract public function toResponse(): Response;

    public function withSession(Session $session): static
    {
        $this->session = $session;
        $this->forgetSession = false;

        return $this;
    }

    public function withoutSession(): static
    {
        $this->session = null;
        $this->forgetSession = true;

        return $this;
    }

    public function session(): ?Session
    {
        return $this->session;
    }

    public function forgetsSession(): bool
    {
        return $this->forgetSession;
    }
}
