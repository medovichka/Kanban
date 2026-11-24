<?php

declare(strict_types=1);

namespace App\Mvc\Request\Parameters;

final class EmailParameter extends StringParameter
{
    public function normalize(mixed $value): mixed
    {
        $value = parent::normalize($value);

        return is_string($value) ? mb_strtolower($value) : $value;
    }

    public function validate(mixed $value): ?string
    {
        $error = parent::validate($value);

        if ($error !== null) {
            return $error;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) === false
            ? sprintf('Поле «%s» должно содержать корректный email.', $this->label)
            : null;
    }
}
