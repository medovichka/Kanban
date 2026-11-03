<?php

declare(strict_types=1);

namespace App\Routing\Exceptions;

use RuntimeException;

final class RouteNotFoundException extends RuntimeException
{
    public function __construct(string $message = 'Маршрут не найден.')
    {
        parent::__construct($message);
    }
}
