<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use App\Session\Session;

final class AuthResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?User $user = null,
        public readonly ?Session $session = null,
        public readonly array $errors = [],
    ) {
    }

    public static function success(User $user, Session $session): self
    {
        return new self(true, $user, $session);
    }

    public static function failure(string ...$errors): self
    {
        return new self(false, null, null, $errors);
    }

    public function firstError(): ?string
    {
        return $this->errors === [] ? null : (string) $this->errors[0];
    }
}
