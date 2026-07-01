<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Domain\Money;
use DomainException;

/**
 * Raised when the machine cannot return the exact change for an amount with the
 * coins it currently holds.
 *
 * Carries the requested amount so callers can decide the business reaction
 * (reject the sale and refund the inserted money) without inspecting state.
 */
final class CannotDispenseChangeException extends DomainException
{
    public function __construct(public readonly Money $amount)
    {
        parent::__construct(
            sprintf('Cannot dispense exact change for %d cents.', $amount->cents)
        );
    }
}
