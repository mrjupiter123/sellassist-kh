<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Social\Models\SocialAiEvaluationCase;
use Illuminate\Database\Seeder;

class AiEvaluationCaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->cases() as $case) {
            SocialAiEvaluationCase::query()->updateOrCreate(
                ['name' => $case['name'], 'locale' => $case['locale']],
                [...$case, 'active' => true],
            );
        }
    }

    /** @return list<array<string, mixed>> */
    private function cases(): array
    {
        return [
            [
                'name' => 'Khmer shirt order with Phnom Penh address',
                'locale' => 'km',
                'messages' => ['ខ្ញុំឈ្មោះ ដារ៉ា លេខ 012345678។ យកអាវខ្មៅ M ចំនួន 2 ផ្ញើទៅភ្នំពេញ។'],
                'catalog' => [[
                    'product_ref' => '10000000-0000-4000-8000-000000000001',
                    'name' => 'Oversize T-Shirt',
                    'sku' => 'SYN-SHIRT',
                    'variants' => [[
                        'variant_ref' => '10000000-0000-4000-8000-000000000002',
                        'name' => 'Black / M',
                        'sku' => 'SYN-SHIRT-BLK-M',
                    ]],
                ]],
                'expected_result' => [
                    'customer_name' => 'ដារ៉ា',
                    'phone' => '012345678',
                    'address' => 'ភ្នំពេញ',
                    'province' => 'ភ្នំពេញ',
                    'district' => null,
                    'commune' => null,
                    'items' => [[
                        'product_ref' => '10000000-0000-4000-8000-000000000001',
                        'variant_ref' => '10000000-0000-4000-8000-000000000002',
                        'quantity' => 2,
                    ]],
                ],
            ],
            [
                'name' => 'English single product order',
                'locale' => 'en',
                'messages' => ['My name is Lina. Please send one red tote bag to Siem Reap. Phone 098765432.'],
                'catalog' => [[
                    'product_ref' => '20000000-0000-4000-8000-000000000001',
                    'name' => 'Canvas Tote Bag',
                    'sku' => 'SYN-TOTE',
                    'variants' => [[
                        'variant_ref' => '20000000-0000-4000-8000-000000000002',
                        'name' => 'Red',
                        'sku' => 'SYN-TOTE-RED',
                    ]],
                ]],
                'expected_result' => [
                    'customer_name' => 'Lina',
                    'phone' => '098765432',
                    'address' => 'Siem Reap',
                    'province' => 'Siem Reap',
                    'district' => null,
                    'commune' => null,
                    'items' => [[
                        'product_ref' => '20000000-0000-4000-8000-000000000001',
                        'variant_ref' => '20000000-0000-4000-8000-000000000002',
                        'quantity' => 1,
                    ]],
                ],
            ],
        ];
    }
}
