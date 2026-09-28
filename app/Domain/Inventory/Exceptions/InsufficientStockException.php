<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Exceptions;

use DomainException;

final class InsufficientStockException extends DomainException
{
    public static function forItem(string $name, int $available, int $requested): self
    {
        return new self("Insufficient stock for {$name}. Available: {$available}; requested: {$requested}.");
    }
}
