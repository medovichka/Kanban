<?php

declare(strict_types=1);

namespace App\Mvc\Request\Parameters;

final class PasswordParameter extends StringParameter
{
    public function __construct(string $name, string $label, int $min = 8, int $max = 255)
    {
        parent::__construct($name, $label, $min, $max, false);
    }
}
