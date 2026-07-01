<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Application\InsertCoin\InsertCoinCommand;
use App\Application\InsertCoin\InsertCoinHandler;
use App\Domain\Coin;
use App\Domain\CoinInventory;
use App\Domain\Money;
use App\Domain\Product;
use App\Domain\ProductSelector;
use App\Domain\VendingMachine;
use App\Infrastructure\Persistence\InMemoryVendingMachineRepository;
use PHPUnit\Framework\TestCase;

final class InsertCoinHandlerTest extends TestCase
{
    public function testInsertCoinIncreasesBalance(): void
    {
        $machine = VendingMachine::create(
            [new Product(ProductSelector::Water, Money::fromCents(65), 5)],
            CoinInventory::empty(),
        );
        $repository = new InMemoryVendingMachineRepository($machine);
        $handler = new InsertCoinHandler($repository);

        ($handler)(new InsertCoinCommand(Coin::TwentyFiveCents));

        self::assertTrue(
            $repository->get()->insertedBalance()->equals(Money::fromCents(25)),
        );
    }

    public function testInsertMultipleCoins(): void
    {
        $machine = VendingMachine::create(
            [new Product(ProductSelector::Water, Money::fromCents(65), 5)],
            CoinInventory::empty(),
        );
        $repository = new InMemoryVendingMachineRepository($machine);
        $handler = new InsertCoinHandler($repository);

        ($handler)(new InsertCoinCommand(Coin::TenCents));
        ($handler)(new InsertCoinCommand(Coin::TenCents));

        self::assertTrue(
            $repository->get()->insertedBalance()->equals(Money::fromCents(20)),
        );
    }
}
