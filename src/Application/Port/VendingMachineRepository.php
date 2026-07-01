<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\VendingMachine;

/**
 * Driven port: how the application persists and retrieves the machine state.
 *
 * It lives in the Application layer because that is the layer that needs it
 * (the use cases load the machine, operate on it and save it back). The concrete
 * technology — in-memory, Redis, MongoDB, SQL — is an Infrastructure detail that
 * implements this interface, so the dependency arrow always points toward the
 * domain (the D in SOLID). Swapping the storage backend never touches the domain
 * or the use cases.
 */
interface VendingMachineRepository
{
    public function get(): VendingMachine;

    public function save(VendingMachine $machine): void;
}
