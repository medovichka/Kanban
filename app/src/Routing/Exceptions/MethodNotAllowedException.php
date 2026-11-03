<?php

declare(strict_types=1);

namespace App\Routing\Exceptions;

use RuntimeException;

final class MethodNotAllowedException extends RuntimeException
{
    public function __construct(public readonly array $allowed)
    {
        parent::__construct('Метод не поддерживается. Разрешены: ' . implode(', ', $allowed));
    }
}
