<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Support\CsvStreamWriter;

class ExportStockMovements
{
    private const MAX_ROWS = 5000;

    public function __construct(
        private readonly ListStockMovements $listMovements,
        private readonly CsvStreamWriter $writer,
    ) {}

    /** @param array<string, mixed> $filters */
    public function isWithinLimit(array $filters): bool
    {
        return $this->listMovements->query($filters)->count() <= self::MAX_ROWS;
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
        yield ['Created at', 'Product', 'Product SKU', 'Variant', 'Variant SKU', 'Type', 'Change', 'Before', 'After', 'Recorded by'];

        foreach ($this->listMovements->query($filters)->orderBy('id')->lazy(500) as $movement) {
            yield [
                $movement->created_at->format('Y-m-d H:i:s'),
                $movement->product->name,
                $movement->product->sku,
                $movement->variant?->display_name,
                $movement->variant?->sku,
                $movement->type->label(),
                $movement->quantity,
                $movement->quantity_before,
                $movement->quantity_after,
                $movement->creator?->name ?: 'System',
            ];
        }
    }
}
