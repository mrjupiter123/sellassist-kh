<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Order\Exceptions\InvalidOrderTotalException;
use App\Domain\Order\Services\OrderCalculator;
use PHPUnit\Framework\TestCase;

class OrderCalculatorTest extends TestCase
{
    public function test_it_calculates_lines_subtotal_discount_delivery_and_total_exactly(): void
    {
        $result = (new OrderCalculator)->calculate([
            ['quantity' => 3, 'unit_price' => '10.25', 'discount' => '0.75'],
            ['quantity' => 1, 'unit_price' => '5.10', 'discount' => '0'],
        ], '2.00', '1.50');

        $this->assertSame(['30.00', '5.10'], $result['lines']);
        $this->assertSame('35.10', $result['subtotal']);
        $this->assertSame('34.60', $result['total']);
    }

    public function test_it_rejects_a_line_discount_above_gross_value(): void
    {
        $this->expectException(InvalidOrderTotalException::class);

        (new OrderCalculator)->calculate([
            ['quantity' => 1, 'unit_price' => '5.00', 'discount' => '5.01'],
        ]);
    }
}
