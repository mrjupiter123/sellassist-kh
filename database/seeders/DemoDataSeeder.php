<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Customer\Enums\CustomerSource;
use App\Domain\Customer\Models\Customer;
use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Order\Actions\ChangeOrderStatus;
use App\Domain\Order\Actions\CreateOrder;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Payment\Actions\RecordPayment;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(
        AdjustStock $adjustStock,
        CreateOrder $createOrder,
        ChangeOrderStatus $changeOrderStatus,
        RecordPayment $recordPayment,
    ): void {
        $admin = User::factory()->create([
            'name' => 'Development Admin',
            'email' => 'admin@sellassist.test',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('admin');

        $customers = Customer::factory()->count(10)->create();
        $products = Product::factory()->count(15)->create();

        foreach ($products as $index => $product) {
            if ($index < 8) {
                foreach (['M', 'L', 'XL'] as $size) {
                    $variant = ProductVariant::factory()->create([
                        'product_id' => $product->id,
                        'size' => $size,
                        'price' => $product->base_price,
                    ]);
                    $adjustStock->execute($product, $variant, 100, StockMovementType::StockIn, $admin, notes: 'Development seed stock');
                }
            } else {
                $adjustStock->execute($product, null, 100, StockMovementType::StockIn, $admin, notes: 'Development seed stock');
            }
        }

        $products->load('variants');

        foreach (range(1, 25) as $number) {
            /** @var Product $product */
            $product = $products->random();
            $variant = $product->variants->isNotEmpty() ? $product->variants->random() : null;

            $order = $createOrder->execute([
                'customer_id' => $customers->random()->id,
                'source' => fake()->randomElement(CustomerSource::cases())->value,
                'status' => OrderStatus::New->value,
                'currency' => 'USD',
                'discount' => $number % 7 === 0 ? '1.00' : '0',
                'delivery_fee' => '1.50',
                'notes' => 'Development sample order',
                'items' => [[
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'quantity' => ($number % 3) + 1,
                    'discount' => '0',
                ]],
            ], $admin);

            if ($number % 5 !== 0) {
                $order = $changeOrderStatus->execute($order, OrderStatus::Confirmed, $admin);
            }

            if ($number % 5 === 1) {
                $order = $changeOrderStatus->execute($order, OrderStatus::Packed, $admin);
            } elseif ($number % 5 === 2) {
                $order = $changeOrderStatus->execute($order, OrderStatus::Packed, $admin);
                $order = $changeOrderStatus->execute($order, OrderStatus::Shipped, $admin);
                $order = $changeOrderStatus->execute($order, OrderStatus::Completed, $admin);
            } elseif ($number % 5 === 3) {
                $order = $changeOrderStatus->execute($order, OrderStatus::Cancelled, $admin);
            }

            if ($number % 4 === 0) {
                $recordPayment->execute($order, [
                    'amount' => $order->total,
                    'currency' => 'USD',
                    'payment_method' => PaymentMethod::Aba->value,
                    'reference' => 'SEED-'.$number,
                ], $admin);
            } elseif ($number % 4 === 1) {
                $recordPayment->execute($order, [
                    'amount' => number_format((float) $order->total / 2, 2, '.', ''),
                    'currency' => 'USD',
                    'payment_method' => PaymentMethod::Cash->value,
                ], $admin);
            }
        }
    }
}
