<?php

declare(strict_types=1);

namespace App\Domain\Customer\Actions;

use App\Domain\Customer\Models\Customer;

final class CreateCustomer
{
    /** @param array<string, mixed> $data */
    public function execute(array $data): Customer
    {
        return Customer::query()->create($data);
    }
}
