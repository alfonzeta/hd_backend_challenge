<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Domain\Money;
use App\Domain\ProductSelector;
use DomainException;

/**
 * Raised when a purchase is attempted with less inserted money than the
 * product's price.
 *
 * Carries the selector together with the required and inserted amounts so
 * callers can tell the customer exactly how much more is needed without
 * inspecting machine state.
 */
final class InsufficientFundsException extends DomainException
{
    public function __construct(
        public readonly ProductSelector $selector,
        public readonly Money $required,
        public readonly Money $inserted,
    ) {
        parent::__construct(
            sprintf(
                'Insufficient funds for "%s": price is %d cents but only %d cents were inserted.',
                $selector->value,
                $required->cents,
                $inserted->cents,
            )
        );
    }
}
