<?php

declare(strict_types=1);

namespace App\Mvc\Response;

use App\Http\Response;

final class HtmlResponse extends ResponseModel
{
    public function __construct(
        private readonly string $content,
        private readonly int $status = 200,
    ) {
    }

    public function toResponse(): Response
    {
        return Response::html($this->content, $this->status);
    }
}
