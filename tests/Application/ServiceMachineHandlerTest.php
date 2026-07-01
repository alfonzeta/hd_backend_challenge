<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Application\InsertCoin\InsertCoinCommand;
use App\Application\InsertCoin\InsertCoinHandler;
use App\Application\ServiceMachine\ServiceMachineCommand;
use App\Application\ServiceMachine\ServiceMachineHandler;
use App\Domain\Coin;
use App\Domain\CoinInventory;
use App\Domain\Money;
use App\Domain\Product;
use App\Domain\ProductSelector;
use App\Domain\VendingMachine;
use App\Infrastructure\Persistence\InMemoryVendingMachineRepository;
use PHPUnit\Framework\TestCase;

final class ServiceMachineHandlerTest extends TestCase
{
    public function testHandlerOrchestratesService(): void
    {
        $machine = VendingMachine::create(
            [
                new Product(ProductSelector::Water, Money::fromCents(65), 0),
                new Product(ProductSelector::Juice, Money::fromCents(100), 0),
            ],
            CoinInventory::empty(),
        );
        $repository = new InMemoryVendingMachineRepository($machine);
        $handler = new ServiceMachineHandler($repository);

        $changeBank = CoinInventory::fromCounts([
            Coin::TwentyFiveCents->value => 4,
            Coin::TenCents->value => 2,
        ]);

        ($handler)(new ServiceMachineCommand(
            [
                ProductSelector::Water->value => 10,
                ProductSelector::Juice->value => 5,
            ],
            $changeBank,
        ));

        $serviced = $repository->get();

        self::assertSame(10, $serviced->product(ProductSelector::Water)->stock());
        self::assertSame(5, $serviced->product(ProductSelector::Juice)->stock());
        self::assertSame(4, $serviced->changeBank()->count(Coin::TwentyFiveCents));
        self::assertSame(2, $serviced->changeBank()->count(Coin::TenCents));
    }

    public function testServiceDoesNotResetInsertedBalance(): void
    {
        $machine = VendingMachine::create(
            [new Product(ProductSelector::Water, Money::fromCents(65), 0)],
            CoinInventory::empty(),
        );
        $repository = new InMemoryVendingMachineRepository($machine);
        $insertHandler = new InsertCoinHandler($repository);
        $serviceHandler = new ServiceMachineHandler($repository);

        ($insertHandler)(new InsertCoinCommand(Coin::TwentyFiveCents));

        ($serviceHandler)(new ServiceMachineCommand(
            [ProductSelector::Water->value => 8],
            CoinInventory::fromCounts([Coin::TenCents->value => 3]),
        ));

        $serviced = $repository->get();

        self::assertTrue(
            $serviced->insertedBalance()->equals(Money::fromCents(25)),
        );
        self::assertSame(8, $serviced->product(ProductSelector::Water)->stock());
    }
}
