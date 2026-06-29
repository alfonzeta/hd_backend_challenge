<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

/**
 * Immutable amount of money expressed as a non-negative integer number of cents.
 *
 * Working with integer cents avoids floating-point rounding errors that would be
 * unacceptable when handling money.
 */
final readonly class Money
{
    private function __construct(public int $cents)
    {
        if ($cents < 0) {
            throw new InvalidArgumentException(
                sprintf('Money cannot be negative, got %d cents.', $cents)
            );
        }
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public function add(Money $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function subtract(Money $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function isGreaterThanOrEqual(Money $other): bool
    {
        return $this->cents >= $other->cents;
    }

    public function equals(Money $other): bool
    {
        return $this->cents === $other->cents;
    }
}
