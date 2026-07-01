<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Exception\CannotDispenseChangeException;
use App\Domain\Exception\InsufficientFundsException;
use App\Domain\Exception\ProductOutOfStockException;
use App\Domain\Exception\UnknownProductException;
use InvalidArgumentException;
use Throwable;
use ValueError;

/**
 * Translates domain and input errors into HTTP status codes. The domain stays
 * transport-agnostic; this adapter owns the HTTP semantics.
 */
final class ExceptionToHttpMapper
{
    public function map(Throwable $throwable): HttpError
    {
        return match (true) {
            $throwable instanceof ProductOutOfStockException,
            $throwable instanceof CannotDispenseChangeException => new HttpError(
                409,
                $throwable->getMessage(),
            ),
            $throwable instanceof InsufficientFundsException => new HttpError(
                400,
                $throwable->getMessage(),
            ),
            $throwable instanceof UnknownProductException => new HttpError(
                404,
                $throwable->getMessage(),
            ),
            $throwable instanceof InvalidArgumentException,
            $throwable instanceof ValueError => new HttpError(
                400,
                $throwable->getMessage(),
            ),
            default => new HttpError(500, 'Internal server error.'),
        };
    }
}
