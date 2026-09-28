<?php

declare(strict_types=1);

namespace App\Domain\Customer\Actions;

use App\Domain\Customer\Models\Customer;

final class UpdateCustomer
{
    /** @param array<string, mixed> $data */
    public function execute(Customer $customer, array $data): Customer
    {
        $customer->update($data);

        return $customer->refresh();
    }
}
