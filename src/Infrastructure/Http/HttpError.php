<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

final readonly class HttpError
{
    public function __construct(
        public int $status,
        public string $message,
    ) {
    }
}
