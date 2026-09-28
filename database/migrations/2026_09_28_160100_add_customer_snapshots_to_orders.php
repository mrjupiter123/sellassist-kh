<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('customer_name')->nullable()->after('customer_id');
            $table->string('customer_phone', 30)->nullable()->after('customer_name');
            $table->text('shipping_address')->nullable()->after('customer_phone');
        });

        DB::table('orders')->orderBy('id')->chunkById(100, function ($orders): void {
            $customers = DB::table('customers')
                ->whereIn('id', $orders->pluck('customer_id'))
                ->get()
                ->keyBy('id');

            foreach ($orders as $order) {
                $customer = $customers->get($order->customer_id);

                if ($customer === null) {
                    continue;
                }

                $address = implode(', ', array_filter([
                    $customer->address,
                    $customer->commune,
                    $customer->district,
                    $customer->province,
                ]));

                DB::table('orders')->where('id', $order->id)->update([
                    'customer_name' => $customer->name,
                    'customer_phone' => $customer->phone,
                    'shipping_address' => $address !== '' ? $address : null,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['customer_name', 'customer_phone', 'shipping_address']);
        });
    }
};
