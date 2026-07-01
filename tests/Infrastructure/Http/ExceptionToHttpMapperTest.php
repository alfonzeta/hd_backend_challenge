<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http;

use App\Domain\Coin;
use App\Domain\Exception\CannotDispenseChangeException;
use App\Domain\Exception\InsufficientFundsException;
use App\Domain\Exception\ProductOutOfStockException;
use App\Domain\Exception\UnknownProductException;
use App\Domain\Money;
use App\Domain\ProductSelector;
use App\Infrastructure\Http\ExceptionToHttpMapper;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ValueError;

final class ExceptionToHttpMapperTest extends TestCase
{
    private ExceptionToHttpMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ExceptionToHttpMapper();
    }

    public function testProductOutOfStockMapsTo409(): void
    {
        $error = $this->mapper->map(new ProductOutOfStockException(ProductSelector::Water));

        self::assertSame(409, $error->status);
    }

    public function testCannotDispenseChangeMapsTo409(): void
    {
        $error = $this->mapper->map(new CannotDispenseChangeException(Money::fromCents(35)));

        self::assertSame(409, $error->status);
    }

    public function testInsufficientFundsMapsTo400(): void
    {
        $error = $this->mapper->map(new InsufficientFundsException(
            ProductSelector::Water,
            Money::fromCents(65),
            Money::fromCents(25),
        ));

        self::assertSame(400, $error->status);
    }

    public function testUnknownProductMapsTo404(): void
    {
        $error = $this->mapper->map(new UnknownProductException(ProductSelector::Juice));

        self::assertSame(404, $error->status);
    }

    public function testInvalidArgumentMapsTo400(): void
    {
        $error = $this->mapper->map(new InvalidArgumentException('bad input'));

        self::assertSame(400, $error->status);
    }

    public function testValueErrorMapsTo400(): void
    {
        try {
            Coin::from(99);
            self::fail('Expected ValueError.');
        } catch (ValueError $e) {
            $error = $this->mapper->map($e);
        }

        self::assertSame(400, $error->status);
    }

    public function testUnhandledExceptionMapsTo500(): void
    {
        $error = $this->mapper->map(new RuntimeException('unexpected'));

        self::assertSame(500, $error->status);
        self::assertSame('Internal server error.', $error->message);
    }
}
