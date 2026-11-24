<?php

declare(strict_types=1);

namespace App\Mvc\Request\Parameters;

use App\Mvc\Request\Parameter;

class StringParameter extends Parameter
{
    public function __construct(
        string $name,
        string $label,
        private readonly int $min = 1,
        private readonly int $max = 255,
        private readonly bool $trim = true,
        private readonly bool $required = true,
    ) {
        parent::__construct($name, $label);
    }

    public function normalize(mixed $value): mixed
    {
        return $this->trim && is_string($value) ? trim($value) : $value;
    }

    public function validate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return $this->required ? $this->requiredMessage() : null;
        }

        if (!is_string($value)) {
            return $this->typeMessage();
        }

        $length = mb_strlen($value);

        if ($length < $this->min) {
            return sprintf('Поле «%s» должно содержать не меньше %d символов.', $this->label, $this->min);
        }

        if ($length > $this->max) {
            return sprintf('Поле «%s» должно содержать не больше %d символов.', $this->label, $this->max);
        }

        return null;
    }
}
