<?php

declare(strict_types=1);

namespace App\Domain\Social\Services;

final class AiExtractionEvaluationScorer
{
    /**
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $actual
     * @return array{score: float, passed: bool, differences: list<string>}
     */
    public function score(array $expected, array $actual): array
    {
        $checks = 0;
        $matched = 0;
        $differences = [];

        foreach (['customer_name', 'phone', 'address', 'province', 'district', 'commune'] as $field) {
            $checks++;
            $expectedValue = $this->normalize($expected[$field] ?? null, $field === 'phone');
            $actualValue = $this->normalize($actual[$field] ?? null, $field === 'phone');
            if ($expectedValue === $actualValue) {
                $matched++;
            } else {
                $differences[] = $field;
            }
        }

        $checks++;
        $expectedItems = $this->normalizeItems($expected['items'] ?? []);
        $actualItems = $this->normalizeItems($actual['items'] ?? []);
        if ($expectedItems === $actualItems) {
            $matched++;
        } else {
            $differences[] = 'items';
        }

        $score = $checks === 0 ? 0.0 : round($matched / $checks, 4);

        return [
            'score' => $score,
            'passed' => $score >= (float) config('social.ai.evaluation_pass_threshold', 0.85),
            'differences' => $differences,
        ];
    }

    private function normalize(mixed $value, bool $phone = false): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value));

        return $phone ? preg_replace('/\D+/', '', $normalized) : $normalized;
    }

    /** @return list<string> */
    private function normalizeItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = collect($items)->filter(fn ($item) => is_array($item))->map(fn (array $item): string => implode('|', [
            (string) ($item['product_ref'] ?? ''),
            (string) ($item['variant_ref'] ?? ''),
            (string) max(1, (int) ($item['quantity'] ?? 1)),
        ]))->sort()->values()->all();

        return $normalized;
    }
}
