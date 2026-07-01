<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\PurchaseProduct\PurchaseProductCommand;
use App\Application\PurchaseProduct\PurchaseProductHandler;
use App\Application\Port\VendingMachineRepository;
use App\Domain\ProductSelector;
use App\Infrastructure\Http\CoinSerializer;
use App\Infrastructure\Http\ExceptionToHttpMapper;
use App\Infrastructure\Http\JsonResponse;
use App\Infrastructure\Http\RequestBody;
use InvalidArgumentException;
use Throwable;

final class PurchaseController
{
    public function __construct(
        private PurchaseProductHandler $handler,
        private VendingMachineRepository $repository,
        private ExceptionToHttpMapper $exceptionMapper,
    ) {
    }

    public function __invoke(?string $rawBody = null): JsonResponse
    {
        try {
            $body = RequestBody::decode($rawBody);
            $selector = $this->parseProduct($body);

            $result = ($this->handler)(new PurchaseProductCommand($selector));

            return JsonResponse::ok([
                'product' => $result->product->value,
                'change' => CoinSerializer::toCents($result->changeCoins),
                'balance' => $this->repository->get()->insertedBalance()->cents,
            ]);
        } catch (Throwable $throwable) {
            return JsonResponse::fromError($this->exceptionMapper->map($throwable));
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function parseProduct(array $body): ProductSelector
    {
        if (!isset($body['product']) || !is_string($body['product'])) {
            throw new InvalidArgumentException('Field "product" (string) is required.');
        }

        return ProductSelector::from($body['product']);
    }
}
