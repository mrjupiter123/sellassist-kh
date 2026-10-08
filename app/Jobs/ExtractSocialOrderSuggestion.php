<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Social\Enums\OrderExtractionStatus;
use App\Domain\Social\Exceptions\SocialOrderExtractionException;
use App\Domain\Social\Models\SocialOrderExtraction;
use App\Domain\Social\Services\OpenAiSocialOrderExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ExtractSocialOrderSuggestion implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly int $extractionId) {}

    public function uniqueId(): string
    {
        return (string) $this->extractionId;
    }

    public function handle(OpenAiSocialOrderExtractor $extractor): void
    {
        $extraction = SocialOrderExtraction::query()->findOrFail($this->extractionId);
        if ($extraction->status === OrderExtractionStatus::Ready) {
            return;
        }

        $extraction->update(['status' => OrderExtractionStatus::Processing, 'error' => null]);

        try {
            $result = $extractor->extract($extraction);

            DB::transaction(function () use ($result): void {
                $locked = SocialOrderExtraction::query()->lockForUpdate()->findOrFail($this->extractionId);
                if ($locked->status === OrderExtractionStatus::Ready) {
                    return;
                }

                $locked->items()->delete();
                foreach ($result->items as $item) {
                    $product = $item['product_ref'] === null ? null : Product::query()
                        ->where('uuid', $item['product_ref'])->where('active', true)->first();
                    $variant = $product === null || $item['variant_ref'] === null ? null : ProductVariant::query()
                        ->where('uuid', $item['variant_ref'])
                        ->where('product_id', $product->id)
                        ->where('active', true)
                        ->first();

                    $locked->items()->create([
                        'product_id' => $product?->id,
                        'product_variant_id' => $variant?->id,
                        'product_query' => $item['product_query'],
                        'variant_query' => $item['variant_query'],
                        'quantity' => $item['quantity'],
                        'confidence' => $item['confidence'],
                    ]);
                }

                $locked->update([
                    'status' => OrderExtractionStatus::Ready,
                    'customer_name' => $result->customerName,
                    'phone' => $result->phone,
                    'address' => $result->address,
                    'province' => $result->province,
                    'district' => $result->district,
                    'commune' => $result->commune,
                    'notes' => $result->notes,
                    'overall_confidence' => $result->overallConfidence,
                    'provider_response_id' => $result->responseId,
                    'input_tokens' => $result->inputTokens,
                    'output_tokens' => $result->outputTokens,
                    'total_tokens' => $result->totalTokens,
                    'result_payload' => $result->payload,
                    'processed_at' => now(),
                    'error' => null,
                ]);
            });
        } catch (Throwable $exception) {
            $safeMessage = $exception instanceof SocialOrderExtractionException
                ? $exception->getMessage()
                : 'The extraction failed unexpectedly. Try again later.';

            SocialOrderExtraction::query()->whereKey($this->extractionId)->update([
                'status' => OrderExtractionStatus::Failed,
                'error' => mb_substr($safeMessage, 0, 1000),
                'processed_at' => now(),
            ]);
            report($exception);

            throw new SocialOrderExtractionException($safeMessage, previous: $exception);
        }
    }
}
