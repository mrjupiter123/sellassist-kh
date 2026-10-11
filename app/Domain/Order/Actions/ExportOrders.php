<?php

declare(strict_types=1);

namespace App\Domain\Order\Actions;

class ExportOrders
{
    private const MAX_ROWS = 5000;

    public function __construct(private readonly ListOrders $listOrders) {}

    /** @param array<string, mixed> $filters */
    public function isWithinLimit(array $filters): bool
    {
        return $this->listOrders->query($filters)->count() <= self::MAX_ROWS;
    }

    /** @param array<string, mixed> $filters */
    public function write(array $filters): void
    {
        $output = fopen('php://output', 'wb');

        if ($output === false) {
            throw new \RuntimeException('Could not open the order export stream.');
        }

        try {
            if (fwrite($output, "\xEF\xBB\xBF") === false) {
                throw new \RuntimeException('Could not write the order export.');
            }

            $this->writeRow($output, ['Order number', 'Created at', 'Customer', 'Source', 'Status', 'Payment status', 'Currency', 'Total']);

            foreach ($this->listOrders->query($filters)->orderBy('id')->lazy(500) as $order) {
                $this->writeRow($output, [
                    $this->safeCell($order->order_number),
                    $order->created_at->format('Y-m-d H:i:s'),
                    $this->safeCell($order->customer_name ?: ($order->customer?->name ?? '')),
                    $order->source->label(),
                    $order->status->label(),
                    $order->payment_status->label(),
                    $order->currency->value,
                    $order->total,
                ]);
            }
        } finally {
            fclose($output);
        }
    }

    private function safeCell(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }

    /** @param resource $output
     * @param  array<int, string>  $row
     */
    private function writeRow($output, array $row): void
    {
        if (fputcsv($output, $row, ',', '"', '') === false) {
            throw new \RuntimeException('Could not write the order export.');
        }
    }
}
