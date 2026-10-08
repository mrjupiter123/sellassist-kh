<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Enums\OrderExtractionStatus;
use App\Domain\Social\Exceptions\SocialOrderExtractionException;
use App\Domain\Social\Models\SocialOrderExtraction;
use App\Domain\Social\Models\SocialOrderExtractionReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ReviewSocialOrderExtraction
{
    /** @param array<string, mixed> $data */
    public function execute(SocialOrderExtraction $extraction, array $data, User $actor): SocialOrderExtractionReview
    {
        return DB::transaction(function () use ($extraction, $data, $actor): SocialOrderExtractionReview {
            $locked = SocialOrderExtraction::query()->lockForUpdate()->findOrFail($extraction->id);
            if ($locked->status !== OrderExtractionStatus::Ready) {
                throw new SocialOrderExtractionException('Only a completed AI suggestion can be reviewed.');
            }

            return $locked->review()->updateOrCreate([], [
                'verdict' => $data['verdict'],
                'customer_fields_correct' => $data['customer_fields_correct'] ?? null,
                'item_matches_correct' => $data['item_matches_correct'] ?? null,
                'quantities_correct' => $data['quantities_correct'] ?? null,
                'notes' => $data['notes'] ?? null,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);
        });
    }
}
