<?php

declare(strict_types=1);

namespace App\Mvc\Request;

abstract class Parameter
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
    ) {
    }

    public function normalize(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }

    abstract public function validate(mixed $value): ?string;

    protected function requiredMessage(): string
    {
        return sprintf('Поле «%s» обязательно.', $this->label);
    }

    protected function typeMessage(): string
    {
        return sprintf('Поле «%s» должно быть строкой.', $this->label);
    }
}
