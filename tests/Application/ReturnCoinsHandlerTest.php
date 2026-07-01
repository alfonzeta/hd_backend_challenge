<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Application\InsertCoin\InsertCoinCommand;
use App\Application\InsertCoin\InsertCoinHandler;
use App\Application\ReturnCoins\ReturnCoinsCommand;
use App\Application\ReturnCoins\ReturnCoinsHandler;
use App\Domain\Coin;
use App\Domain\CoinInventory;
use App\Domain\Money;
use App\Domain\Product;
use App\Domain\ProductSelector;
use App\Domain\VendingMachine;
use App\Infrastructure\Persistence\InMemoryVendingMachineRepository;
use PHPUnit\Framework\TestCase;

final class ReturnCoinsHandlerTest extends TestCase
{
    public function testReturnAllInsertedCoins(): void
    {
        $machine = VendingMachine::create(
            [new Product(ProductSelector::Water, Money::fromCents(65), 5)],
            CoinInventory::empty(),
        );
        $repository = new InMemoryVendingMachineRepository($machine);
        $insertHandler = new InsertCoinHandler($repository);
        $returnHandler = new ReturnCoinsHandler($repository);

        ($insertHandler)(new InsertCoinCommand(Coin::TenCents));
        ($insertHandler)(new InsertCoinCommand(Coin::TenCents));

        $returned = ($returnHandler)(new ReturnCoinsCommand());

        self::assertSame([Coin::TenCents, Coin::TenCents], $returned);
    }

    public function testBalanceResetAfterReturn(): void
    {
        $machine = VendingMachine::create(
            [new Product(ProductSelector::Water, Money::fromCents(65), 5)],
            CoinInventory::empty(),
        );
        $repository = new InMemoryVendingMachineRepository($machine);
        $insertHandler = new InsertCoinHandler($repository);
        $returnHandler = new ReturnCoinsHandler($repository);

        ($insertHandler)(new InsertCoinCommand(Coin::TenCents));
        ($insertHandler)(new InsertCoinCommand(Coin::TenCents));
        ($returnHandler)(new ReturnCoinsCommand());

        self::assertTrue(
            $repository->get()->insertedBalance()->equals(Money::fromCents(0)),
        );
    }

    public function testReturnWithNoInsertedMoney(): void
    {
        $machine = VendingMachine::create(
            [new Product(ProductSelector::Water, Money::fromCents(65), 5)],
            CoinInventory::empty(),
        );
        $repository = new InMemoryVendingMachineRepository($machine);
        $returnHandler = new ReturnCoinsHandler($repository);

        $returned = ($returnHandler)(new ReturnCoinsCommand());

        self::assertSame([], $returned);
        self::assertTrue(
            $repository->get()->insertedBalance()->equals(Money::fromCents(0)),
        );
    }
}
