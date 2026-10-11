<?php

declare(strict_types=1);

namespace App\Domain\Customer\Actions;

use App\Domain\Customer\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListCustomerOrders
{
    public function execute(Customer $customer): LengthAwarePaginator
    {
        return $customer->orders()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);
    }
}
