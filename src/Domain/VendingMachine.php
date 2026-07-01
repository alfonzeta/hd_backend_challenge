<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Exception\CannotDispenseChangeException;
use App\Domain\Exception\InsufficientFundsException;
use App\Domain\Exception\ProductOutOfStockException;
use App\Domain\Exception\UnknownProductException;

/**
 * Aggregate root of the vending machine: the single entry point for mutating
 * machine state and the boundary where every business invariant is enforced.
 *
 * It coordinates the available products, the change bank ({@see CoinInventory})
 * and the money inserted in the current session, delegating the "which coins to
 * return" decision to an injected {@see ChangeStrategy} port. No path can leave
 * the machine in an inconsistent state without raising a domain exception.
 *
 * The inserted balance is transient session state, modelled here for a
 * single-customer-at-a-time machine. A concurrent, multi-customer scenario
 * would extract it into a dedicated Session/Transaction; this is the natural
 * extension point.
 */
final class VendingMachine
{
    /**
     * @param array<string,Product> $products keyed by selector value
     * @param list<Coin>            $insertedCoins coins inserted this session, in order
     */
    private function __construct(
        private array $products,
        private CoinInventory $changeBank,
        private readonly ChangeStrategy $changeStrategy,
        private array $insertedCoins,
        private Money $insertedBalance,
    ) {
    }

    /**
     * @param list<Product> $products the products this machine offers
     */
    public static function create(
        array $products,
        CoinInventory $changeBank,
        ?ChangeStrategy $changeStrategy = null,
    ): self {
        $bySelector = [];
        foreach ($products as $product) {
            $bySelector[$product->selector->value] = $product;
        }

        return new self(
            $bySelector,
            $changeBank,
            $changeStrategy ?? new GreedyChangeStrategy(),
            [],
            Money::fromCents(0),
        );
    }

    /**
     * Accepts a coin: it counts toward the session balance and is added to the
     * change bank so it becomes available as change for this very purchase.
     */
    public function insert(Coin $coin): void
    {
        $this->insertedBalance = $this->insertedBalance->add($coin->toMoney());
        $this->insertedCoins[] = $coin;
        $this->changeBank = $this->changeBank->add($coin);
    }

    /**
     * Refunds the exact coins inserted this session (RETURN-COIN), removing them
     * from the change bank and clearing the session balance.
     *
     * @return list<Coin> the coins handed back, in the order they were inserted
     */
    public function returnInsertedCoins(): array
    {
        $coins = $this->insertedCoins;

        foreach ($coins as $coin) {
            $this->changeBank = $this->changeBank->remove($coin);
        }

        $this->resetSession();

        return $coins;
    }

    /**
     * Vends the selected product, returning the product and any change due.
     *
     * The whole operation is atomic: if any invariant fails it throws before
     * mutating state, so the inserted money is preserved and the customer can
     * still RETURN-COIN.
     *
     * @throws UnknownProductException        when the selector is not stocked
     * @throws ProductOutOfStockException     when no units remain
     * @throws InsufficientFundsException     when the inserted money is below the price
     * @throws CannotDispenseChangeException  when exact change cannot be made
     */
    public function purchase(ProductSelector $selector): PurchaseResult
    {
        $product = $this->product($selector);

        if (!$product->isAvailable()) {
            throw new ProductOutOfStockException($selector);
        }

        if (!$this->insertedBalance->isGreaterThanOrEqual($product->price)) {
            throw new InsufficientFundsException(
                $selector,
                $product->price,
                $this->insertedBalance,
            );
        }

        $changeDue = $this->insertedBalance->subtract($product->price);

        // Computed against the bank, which already includes the inserted coins.
        // Throws CannotDispenseChangeException before any state changes.
        $changeCoins = $this->changeStrategy->calculate($changeDue, $this->changeBank);

        $product->dispenseOne();

        foreach ($changeCoins as $coin) {
            $this->changeBank = $this->changeBank->remove($coin);
        }

        $this->resetSession();

        return new PurchaseResult($selector, $changeCoins);
    }

    public function insertedBalance(): Money
    {
        return $this->insertedBalance;
    }

    /** @return list<Coin> */
    public function insertedCoins(): array
    {
        return $this->insertedCoins;
    }

    public function changeBank(): CoinInventory
    {
        return $this->changeBank;
    }

    /**
     * Maintenance operation (SERVICE): replaces product stock and the change bank
     * with the commanded values. Does not touch the current session balance.
     *
     * @param array<string, int> $productStock selector value (e.g. WATER) => stock count
     */
    public function service(array $productStock, CoinInventory $changeBank): void
    {
        foreach ($productStock as $selectorValue => $stock) {
            $selector = ProductSelector::from($selectorValue);
            $product = $this->product($selector);
            $this->products[$selector->value] = new Product(
                $product->selector,
                $product->price,
                $stock,
            );
        }

        $this->changeBank = $changeBank;
    }

    /**
     * @throws UnknownProductException when the machine does not stock the selector
     */
    public function product(ProductSelector $selector): Product
    {
        return $this->products[$selector->value]
            ?? throw new UnknownProductException($selector);
    }

    private function resetSession(): void
    {
        $this->insertedCoins = [];
        $this->insertedBalance = Money::fromCents(0);
    }
}
