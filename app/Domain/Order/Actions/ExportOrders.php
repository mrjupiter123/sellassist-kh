<?php

declare(strict_types=1);

namespace App\Domain\Order\Actions;

use App\Support\CsvStreamWriter;

class ExportOrders
{
    private const MAX_ROWS = 5000;

    public function __construct(
        private readonly ListOrders $listOrders,
        private readonly CsvStreamWriter $writer,
    ) {}

    /** @param array<string, mixed> $filters */
    public function isWithinLimit(array $filters): bool
    {
        return $this->listOrders->query($filters)->count() <= self::MAX_ROWS;
    }

    /** @param array<string, mixed> $filters */
    public function write(array $filters): void
    {
        $this->writer->write($this->rows($filters));
    }

    /** @param array<string, mixed> $filters
     * @return \Generator<array<int, int|float|string|null>>
     */
    private function rows(array $filters): \Generator
    {
        yield ['Order number', 'Created at', 'Customer', 'Source', 'Status', 'Payment status', 'Currency', 'Total'];

        foreach ($this->listOrders->query($filters)->orderBy('id')->lazy(500) as $order) {
            yield [
                $order->order_number,
                $order->created_at->format('Y-m-d H:i:s'),
                $order->customer_name ?: ($order->customer?->name ?? ''),
                $order->source->label(),
                $order->status->label(),
                $order->payment_status->label(),
                $order->currency->value,
                $order->total,
            ];
        }
    }
}
