<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * HTTP JSON payload plus status code. Controllers build this; the front
 * controller emits it. Keeping emission separate makes controllers testable
 * without terminating the process.
 */
final readonly class JsonResponse
{
    /**
     * @param array<string, mixed> $body
     */
    public function __construct(
        public int $status,
        public array $body,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function ok(array $body, int $status = 200): self
    {
        return new self($status, $body);
    }

    public static function fromError(HttpError $error): self
    {
        return new self($error->status, ['error' => $error->message]);
    }

    public function emit(): never
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->body, JSON_THROW_ON_ERROR);

        exit;
    }
}
