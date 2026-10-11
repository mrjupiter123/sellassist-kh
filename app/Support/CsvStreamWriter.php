<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

class CsvStreamWriter
{
    /** @param iterable<array<int, int|float|string|null>> $rows */
    public function write(iterable $rows): void
    {
        $output = fopen('php://output', 'wb');

        if ($output === false) {
            throw new RuntimeException('Could not open the CSV export stream.');
        }

        try {
            if (fwrite($output, "\xEF\xBB\xBF") === false) {
                throw new RuntimeException('Could not write the CSV export.');
            }

            foreach ($rows as $row) {
                $safe = array_map($this->safeCell(...), $row);

                if (fputcsv($output, $safe, ',', '"', '') === false) {
                    throw new RuntimeException('Could not write the CSV export.');
                }
            }
        } finally {
            fclose($output);
        }
    }

    private function safeCell(int|float|string|null $value): string
    {
        if (! is_string($value)) {
            return (string) $value;
        }

        return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
