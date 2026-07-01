<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Domain\ProductSelector;
use DomainException;

/**
 * Raised when a product is requested but no units remain in stock.
 *
 * Carries the selector so callers can report exactly which product is
 * unavailable without inspecting machine state.
 */
final class ProductOutOfStockException extends DomainException
{
    public function __construct(public readonly ProductSelector $selector)
    {
        parent::__construct(
            sprintf('Product "%s" is out of stock.', $selector->value)
        );
    }
}
