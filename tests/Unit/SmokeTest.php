<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Money;
use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function testSmoke(): void
    {
        self::assertTrue(class_exists(Money::class));
    }
}
