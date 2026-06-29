<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Coin;
use App\Domain\CoinInventory;
use App\Domain\Exception\CannotDispenseChangeException;
use App\Domain\Exception\InsufficientFundsException;
use App\Domain\Exception\ProductOutOfStockException;
use App\Domain\Money;
use App\Domain\Product;
use App\Domain\ProductSelector;
use App\Domain\VendingMachine;
use PHPUnit\Framework\TestCase;

final class VendingMachineTest extends TestCase
{
    private const WATER_PRICE = 65;
    private const SODA_PRICE = 150;

    public function testInsertAccumulatesBalance(): void
    {
        $machine = $this->machine();

        $machine->insert(Coin::TenCents);
        $machine->insert(Coin::TenCents);

        self::assertTrue($machine->insertedBalance()->equals(Money::fromCents(20)));
    }

    public function testReturnInsertedCoinsRefundsExactCoinsAndClearsBalance(): void
    {
        $machine = $this->machine();
        $machine->insert(Coin::TwentyFiveCents);
        $machine->insert(Coin::TenCents);

        $returned = $machine->returnInsertedCoins();

        self::assertSame([Coin::TwentyFiveCents, Coin::TenCents], $returned);
        self::assertTrue($machine->insertedBalance()->equals(Money::fromCents(0)));
    }

    public function testReturnInsertedCoinsLeavesChangeBankUnchanged(): void
    {
        $machine = $this->machine(changeBank: CoinInventory::empty());
        $machine->insert(Coin::TwentyFiveCents);

        // While inserted, the coin is part of the bank.
        self::assertSame(1, $machine->changeBank()->count(Coin::TwentyFiveCents));

        $machine->returnInsertedCoins();

        // After refund, the bank is back to its original state.
        self::assertSame(0, $machine->changeBank()->count(Coin::TwentyFiveCents));
    }

    public function testPurchaseInsufficientFundsThrowsAndKeepsBalance(): void
    {
        $machine = $this->machine();
        $machine->insert(Coin::TwentyFiveCents);

        try {
            $machine->purchase(ProductSelector::Water);
            self::fail('Expected InsufficientFundsException.');
        } catch (InsufficientFundsException $e) {
            self::assertSame(ProductSelector::Water, $e->selector);
            self::assertSame(self::WATER_PRICE, $e->required->cents);
            self::assertSame(25, $e->inserted->cents);
        }

        // Balance is preserved so the customer can RETURN-COIN.
        self::assertTrue($machine->insertedBalance()->equals(Money::fromCents(25)));
    }

    public function testPurchaseOutOfStockThrows(): void
    {
        $machine = VendingMachine::create(
            [new Product(ProductSelector::Water, Money::fromCents(self::WATER_PRICE), 0)],
            CoinInventory::fromCounts([Coin::TwentyFiveCents->value => 4]),
        );
        $machine->insert(Coin::OneEuro);

        $this->expectException(ProductOutOfStockException::class);

        $machine->purchase(ProductSelector::Water);
    }

    public function testPurchaseCannotDispenseChangeThrowsAndKeepsState(): void
    {
        // Owe 35 in change but the bank only holds the inserted 1.00 coin.
        $machine = VendingMachine::create(
            [new Product(ProductSelector::Water, Money::fromCents(self::WATER_PRICE), 1)],
            CoinInventory::empty(),
        );
        $machine->insert(Coin::OneEuro);

        try {
            $machine->purchase(ProductSelector::Water);
            self::fail('Expected CannotDispenseChangeException.');
        } catch (CannotDispenseChangeException) {
            // expected
        }

        // No product dispensed, balance kept for refund.
        self::assertSame(1, $machine->product(ProductSelector::Water)->stock());
        self::assertTrue($machine->insertedBalance()->equals(Money::fromCents(100)));
    }

    public function testSuccessfulPurchaseWithChange(): void
    {
        // Spec Example 3: insert 1.00, buy Water 0.65, get 0.25 + 0.10 back.
        $machine = VendingMachine::create(
            [new Product(ProductSelector::Water, Money::fromCents(self::WATER_PRICE), 1)],
            CoinInventory::fromCounts([
                Coin::TwentyFiveCents->value => 1,
                Coin::TenCents->value => 1,
            ]),
        );
        $machine->insert(Coin::OneEuro);

        $result = $machine->purchase(ProductSelector::Water);

        self::assertSame(ProductSelector::Water, $result->product);
        self::assertEqualsCanonicalizing(
            [Coin::TwentyFiveCents, Coin::TenCents],
            $result->changeCoins,
        );
        self::assertSame(0, $machine->product(ProductSelector::Water)->stock());
        self::assertTrue($machine->insertedBalance()->equals(Money::fromCents(0)));
        // The 1.00 coin stayed in the bank; the 25 and 10 were dispensed as change.
        self::assertSame(1, $machine->changeBank()->count(Coin::OneEuro));
        self::assertSame(0, $machine->changeBank()->count(Coin::TwentyFiveCents));
        self::assertSame(0, $machine->changeBank()->count(Coin::TenCents));
    }

    public function testSuccessfulPurchaseExactPaymentReturnsNoChange(): void
    {
        $machine = VendingMachine::create(
            [new Product(ProductSelector::Soda, Money::fromCents(self::SODA_PRICE), 1)],
            CoinInventory::empty(),
        );
        $machine->insert(Coin::OneEuro);
        $machine->insert(Coin::TwentyFiveCents);
        $machine->insert(Coin::TwentyFiveCents);

        $result = $machine->purchase(ProductSelector::Soda);

        self::assertSame([], $result->changeCoins);
        self::assertTrue($machine->insertedBalance()->equals(Money::fromCents(0)));
    }

    private function machine(?CoinInventory $changeBank = null): VendingMachine
    {
        return VendingMachine::create(
            [
                new Product(ProductSelector::Water, Money::fromCents(self::WATER_PRICE), 5),
                new Product(ProductSelector::Soda, Money::fromCents(self::SODA_PRICE), 5),
            ],
            $changeBank ?? CoinInventory::fromCounts([
                Coin::TwentyFiveCents->value => 10,
                Coin::TenCents->value => 10,
                Coin::FiveCents->value => 10,
            ]),
        );
    }
}
