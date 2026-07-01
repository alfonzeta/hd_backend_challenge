<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use InvalidArgumentException;

/**
 * Parses JSON request bodies for POST endpoints.
 */
final class RequestBody
{
    /**
     * @return array<string, mixed>
     */
    public static function decode(?string $rawBody = null): array
    {
        $rawBody ??= (string) file_get_contents('php://input');

        if ($rawBody === '') {
            return [];
        }

        $data = json_decode($rawBody, true);

        if (!is_array($data)) {
            throw new InvalidArgumentException('Request body must be valid JSON.');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
