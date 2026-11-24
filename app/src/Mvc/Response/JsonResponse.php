<?php

declare(strict_types=1);

namespace App\Mvc\Response;

use App\Http\Response;

final class JsonResponse extends ResponseModel
{
    public function __construct(
        private readonly string|array|bool|int|float|null $data,
        private readonly int $status = 200,
    ) {
    }

    public function toResponse(): Response
    {
        return Response::json($this->data, $this->status);
    }
}
