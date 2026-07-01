<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\InsertCoin\InsertCoinCommand;
use App\Application\InsertCoin\InsertCoinHandler;
use App\Application\Port\VendingMachineRepository;
use App\Domain\Coin;
use App\Infrastructure\Http\ExceptionToHttpMapper;
use App\Infrastructure\Http\JsonResponse;
use App\Infrastructure\Http\RequestBody;
use InvalidArgumentException;
use Throwable;

final class CoinController
{
    public function __construct(
        private InsertCoinHandler $handler,
        private VendingMachineRepository $repository,
        private ExceptionToHttpMapper $exceptionMapper,
    ) {
    }

    public function __invoke(?string $rawBody = null): JsonResponse
    {
        try {
            $body = RequestBody::decode($rawBody);
            $coin = $this->parseCoin($body);

            ($this->handler)(new InsertCoinCommand($coin));

            return JsonResponse::ok([
                'balance' => $this->repository->get()->insertedBalance()->cents,
            ]);
        } catch (Throwable $throwable) {
            return JsonResponse::fromError($this->exceptionMapper->map($throwable));
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function parseCoin(array $body): Coin
    {
        if (!array_key_exists('coin', $body) || !is_int($body['coin'])) {
            throw new InvalidArgumentException('Field "coin" (integer cents) is required.');
        }

        return Coin::from($body['coin']);
    }
}
