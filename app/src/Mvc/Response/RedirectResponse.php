<?php

declare(strict_types=1);

namespace App\Mvc\Response;

use App\Http\Response;

final class RedirectResponse extends ResponseModel
{
    public function __construct(
        private readonly string $location,
        private readonly int $status = 303,
    ) {
    }

    public function toResponse(): Response
    {
        return Response::redirect($this->location, $this->status);
    }
}
