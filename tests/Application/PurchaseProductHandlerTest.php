<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Application\InsertCoin\InsertCoinCommand;
use App\Application\InsertCoin\InsertCoinHandler;
use App\Application\PurchaseProduct\PurchaseProductCommand;
use App\Application\PurchaseProduct\PurchaseProductHandler;
use App\Domain\Coin;
use App\Domain\CoinInventory;
use App\Domain\Exception\CannotDispenseChangeException;
use App\Domain\Exception\InsufficientFundsException;
use App\Domain\Exception\ProductOutOfStockException;
use App\Domain\Money;
use App\Domain\Product;
use App\Domain\ProductSelector;
use App\Domain\VendingMachine;
use App\Infrastructure\Persistence\InMemoryVendingMachineRepository;
use PHPUnit\Framework\TestCase;

final class PurchaseProductHandlerTest extends TestCase
{
    private const WATER_PRICE = 65;
    private const SODA_PRICE = 150;

    public function testExample1BuySodaExactChange(): void
    {
        $repository = $this->repository();
        $insertHandler = new InsertCoinHandler($repository);
        $purchaseHandler = new PurchaseProductHandler($repository);

        ($insertHandler)(new InsertCoinCommand(Coin::OneEuro));
        ($insertHandler)(new InsertCoinCommand(Coin::TwentyFiveCents));
        ($insertHandler)(new InsertCoinCommand(Coin::TwentyFiveCents));

        $result = ($purchaseHandler)(new PurchaseProductCommand(ProductSelector::Soda));

        self::assertSame(ProductSelector::Soda, $result->product);
        self::assertSame([], $result->changeCoins);
        self::assertTrue(
            $repository->get()->insertedBalance()->equals(Money::fromCents(0)),
        );
        self::assertSame(4, $repository->get()->product(ProductSelector::Soda)->stock());
    }

    public function testExample3BuyWaterWithChange(): void
    {
        $machine = VendingMachine::create(
            [
                new Product(ProductSelector::Water, Money::fromCents(self::WATER_PRICE), 1),
                new Product(ProductSelector::Soda, Money::fromCents(self::SODA_PRICE), 5),
            ],
            CoinInventory::fromCounts([
                Coin::TwentyFiveCents->value => 1,
                Coin::TenCents->value => 1,
            ]),
        );
        $repository = new InMemoryVendingMachineRepository($machine);
        $insertHandler = new InsertCoinHandler($repository);
        $purchaseHandler = new PurchaseProductHandler($repository);

        ($insertHandler)(new InsertCoinCommand(Coin::OneEuro));

        $result = ($purchaseHandler)(new PurchaseProductCommand(ProductSelector::Water));

        self::assertSame(ProductSelector::Water, $result->product);
        self::assertEqualsCanonicalizing(
            [Coin::TwentyFiveCents, Coin::TenCents],
            $result->changeCoins,
        );
        self::assertSame(0, $repository->get()->product(ProductSelector::Water)->stock());
        self::assertTrue(
            $repository->get()->insertedBalance()->equals(Money::fromCents(0)),
        );
    }

    public function testInsufficientFunds(): void
    {
        $repository = $this->repository();
        $insertHandler = new InsertCoinHandler($repository);
        $purchaseHandler = new PurchaseProductHandler($repository);

        ($insertHandler)(new InsertCoinCommand(Coin::TwentyFiveCents));

        try {
            ($purchaseHandler)(new PurchaseProductCommand(ProductSelector::Water));
            self::fail('Expected InsufficientFundsException.');
        } catch (InsufficientFundsException $e) {
            self::assertSame(ProductSelector::Water, $e->selector);
        }

        self::assertSame(5, $repository->get()->product(ProductSelector::Water)->stock());
        self::assertTrue(
            $repository->get()->insertedBalance()->equals(Money::fromCents(25)),
        );
    }

    public function testOutOfStock(): void
    {
        $machine = VendingMachine::create(
            [new Product(ProductSelector::Water, Money::fromCents(self::WATER_PRICE), 0)],
            CoinInventory::fromCounts([Coin::TwentyFiveCents->value => 4]),
        );
        $repository = new InMemoryVendingMachineRepository($machine);
        $insertHandler = new InsertCoinHandler($repository);
        $purchaseHandler = new PurchaseProductHandler($repository);

        ($insertHandler)(new InsertCoinCommand(Coin::OneEuro));

        $this->expectException(ProductOutOfStockException::class);

        ($purchaseHandler)(new PurchaseProductCommand(ProductSelector::Water));
    }

    public function testCannotDispenseChange(): void
    {
        $machine = VendingMachine::create(
            [new Product(ProductSelector::Water, Money::fromCents(self::WATER_PRICE), 1)],
            CoinInventory::empty(),
        );
        $repository = new InMemoryVendingMachineRepository($machine);
        $insertHandler = new InsertCoinHandler($repository);
        $purchaseHandler = new PurchaseProductHandler($repository);

        ($insertHandler)(new InsertCoinCommand(Coin::OneEuro));

        try {
            ($purchaseHandler)(new PurchaseProductCommand(ProductSelector::Water));
            self::fail('Expected CannotDispenseChangeException.');
        } catch (CannotDispenseChangeException) {
            // expected
        }

        self::assertSame(1, $repository->get()->product(ProductSelector::Water)->stock());
        self::assertTrue(
            $repository->get()->insertedBalance()->equals(Money::fromCents(100)),
        );
    }

    private function repository(): InMemoryVendingMachineRepository
    {
        $machine = VendingMachine::create(
            [
                new Product(ProductSelector::Water, Money::fromCents(self::WATER_PRICE), 5),
                new Product(ProductSelector::Soda, Money::fromCents(self::SODA_PRICE), 5),
            ],
            CoinInventory::fromCounts([
                Coin::TwentyFiveCents->value => 10,
                Coin::TenCents->value => 10,
                Coin::FiveCents->value => 10,
            ]),
        );

        return new InMemoryVendingMachineRepository($machine);
    }
}
