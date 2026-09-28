<?php

declare(strict_types=1);

namespace App\Domain\Order\Events;

use App\Domain\Order\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OrderCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Order $order) {}
}
