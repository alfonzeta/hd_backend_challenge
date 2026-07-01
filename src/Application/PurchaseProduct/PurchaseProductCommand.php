<?php

declare(strict_types=1);

namespace App\Application\PurchaseProduct;

use App\Domain\ProductSelector;

final readonly class PurchaseProductCommand
{
    public function __construct(public ProductSelector $selector)
    {
    }
}
