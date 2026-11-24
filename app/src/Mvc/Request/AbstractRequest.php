<?php

declare(strict_types=1);

namespace App\Mvc\Request;

use App\Http\Request;
use App\Mvc\Response\ResponseModel;

abstract class AbstractRequest
{
    private array $values = [];

    private array $errors = [];

    public function __construct(protected readonly Request $request)
    {
        $this->validate();
    }

    abstract protected function parameters(): array;

    abstract public function execute(): ResponseModel;

    public function request(): Request
    {
        return $this->request;
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function values(): array
    {
        return $this->values;
    }

    public function value(string $name, mixed $default = null): mixed
    {
        return $this->values[$name] ?? $default;
    }

    protected function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    private function validate(): void
    {
        foreach ($this->parameters() as $parameter) {
            $value = $parameter->normalize($this->request->body[$parameter->name] ?? null);
            $this->values[$parameter->name] = $value;

            $error = $parameter->validate($value);

            if ($error !== null) {
                $this->errors[] = $error;
            }
        }
    }
}
