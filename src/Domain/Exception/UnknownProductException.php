<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Domain\ProductSelector;
use DomainException;

/**
 * Raised when a selector is requested that the machine was not configured with.
 *
 * In practice {@see ProductSelector} already constrains the set of valid
 * selectors at the type level; this guards the remaining case where a machine
 * instance simply does not stock that selector.
 */
final class UnknownProductException extends DomainException
{
    public function __construct(public readonly ProductSelector $selector)
    {
        parent::__construct(
            sprintf('Machine does not offer product "%s".', $selector->value)
        );
    }
}
