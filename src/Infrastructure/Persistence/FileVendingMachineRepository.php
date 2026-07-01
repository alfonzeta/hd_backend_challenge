<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\VendingMachineRepository;
use App\Domain\Coin;
use App\Domain\CoinInventory;
use App\Domain\Money;
use App\Domain\Product;
use App\Domain\ProductSelector;
use App\Domain\VendingMachine;

/**
 * File-backed adapter for {@see VendingMachineRepository}.
 *
 * Persists machine state as a JSON file so it survives PHP's shared-nothing
 * request lifecycle. Each HTTP request reads state at the start and writes it
 * back when the use case calls save().
 *
 * Serialisation format:
 *   - products: selector => { price (cents), stock }
 *   - changeBank: denomination (cents) => count  ← without inserted coins
 *   - insertedCoinValues: list of cent values inserted in the current session
 *
 * Inserted coins are stored separately from the change bank because
 * VendingMachine::insert() adds them to both counters; re-inserting them via
 * the domain method on hydration keeps all three internal fields consistent.
 *
 * File access is protected by LOCK_SH on read and LOCK_EX on write to avoid
 * torn reads under concurrent requests.
 */
final class FileVendingMachineRepository implements VendingMachineRepository
{
    private readonly string $path;

    public function __construct(?string $storagePath = null)
    {
        $this->path = $storagePath
            ?? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vending-machine-state.json';
    }

    public function get(): VendingMachine
    {
        if (!file_exists($this->path)) {
            return $this->standardMachine();
        }

        $fp = fopen($this->path, 'r');
        if ($fp === false) {
            return $this->standardMachine();
        }

        flock($fp, LOCK_SH);
        $raw = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($raw === false || $raw === '') {
            return $this->standardMachine();
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return $this->standardMachine();
        }

        return $this->hydrate($data);
    }

    public function save(VendingMachine $machine): void
    {
        $insertedCoins = $machine->insertedCoins();

        // Derive the base change bank without the currently-inserted coins.
        // VendingMachine::insert() deposits them into the bank, so they must be
        // excluded here to avoid double-counting when hydrating on the next request.
        $baseBank = $machine->changeBank();
        foreach ($insertedCoins as $coin) {
            if ($baseBank->has($coin)) {
                $baseBank = $baseBank->remove($coin);
            }
        }

        $products = [];
        foreach (ProductSelector::cases() as $selector) {
            $product = $machine->product($selector);
            $products[$selector->value] = [
                'price' => $product->price->cents,
                'stock' => $product->stock(),
            ];
        }

        $coinCounts = [];
        foreach (Coin::cases() as $coin) {
            $count = $baseBank->count($coin);
            if ($count > 0) {
                $coinCounts[$coin->value] = $count;
            }
        }

        $state = [
            'products'           => $products,
            'changeBank'         => $coinCounts,
            'insertedCoinValues' => array_map(static fn (Coin $c) => $c->value, $insertedCoins),
        ];

        file_put_contents(
            $this->path,
            json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            LOCK_EX,
        );
    }

    /**
     * @param array<mixed, mixed> $data
     */
    private function hydrate(array $data): VendingMachine
    {
        /** @var array<string, array{price: int, stock: int}> $productsData */
        $productsData = is_array($data['products'] ?? null) ? $data['products'] : [];

        $products = [];
        foreach ($productsData as $selectorValue => $info) {
            $products[] = new Product(
                ProductSelector::from($selectorValue),
                Money::fromCents((int) $info['price']),
                (int) $info['stock'],
            );
        }

        // JSON decodes numeric-string keys as strings; cast back to int for CoinInventory.
        $counts = [];
        $changeBankRaw = is_array($data['changeBank'] ?? null) ? $data['changeBank'] : [];
        foreach ($changeBankRaw as $denomination => $coinCount) {
            if (is_numeric($coinCount) && is_numeric($denomination)) {
                $counts[(int) $denomination] = (int) (float) $coinCount;
            }
        }

        $changeBank = CoinInventory::fromCounts($counts);

        $machine = VendingMachine::create($products, $changeBank);

        // Re-insert session coins through the domain method so that
        // insertedCoins, insertedBalance and changeBank stay consistent.
        $insertedRaw = is_array($data['insertedCoinValues'] ?? null) ? $data['insertedCoinValues'] : [];
        foreach ($insertedRaw as $cents) {
            if (is_numeric($cents)) {
                $machine->insert(Coin::from((int) (float) $cents));
            }
        }

        return $machine;
    }

    private function standardMachine(): VendingMachine
    {
        return VendingMachine::create(
            [
                new Product(ProductSelector::Water, Money::fromCents(65), 5),
                new Product(ProductSelector::Juice, Money::fromCents(100), 5),
                new Product(ProductSelector::Soda, Money::fromCents(150), 5),
            ],
            CoinInventory::fromCounts([
                Coin::TwentyFiveCents->value => 10,
                Coin::TenCents->value        => 10,
                Coin::FiveCents->value       => 10,
            ]),
        );
    }
}
