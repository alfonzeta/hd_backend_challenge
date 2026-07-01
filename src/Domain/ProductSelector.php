<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * The catalogue of products a customer can select.
 *
 * Modelling the selector as a backed enum makes the set of products a closed,
 * type-safe domain concept: only these three selectors exist, and each maps to
 * the action string used by the machine (e.g. GET-WATER).
 */
enum ProductSelector: string
{
    case Water = 'WATER';
    case Juice = 'JUICE';
    case Soda = 'SODA';
}
