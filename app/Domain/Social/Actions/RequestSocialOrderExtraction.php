<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Enums\ConversationStatus;
use App\Domain\Social\Enums\MessageDirection;
use App\Domain\Social\Enums\OrderExtractionStatus;
use App\Domain\Social\Exceptions\SocialOrderExtractionException;
use App\Domain\Social\Models\SocialAiExtractionProfile;
use App\Domain\Social\Models\SocialConversation;
use App\Domain\Social\Models\SocialOrderExtraction;
use App\Domain\Social\Services\AiExtractionPrompt;
use App\Jobs\ExtractSocialOrderSuggestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RequestSocialOrderExtraction
{
    public function __construct(private readonly AiExtractionPrompt $prompt) {}

    public function execute(SocialConversation $conversation, User $actor): SocialOrderExtraction
    {
        if (! config('social.ai.enabled') || blank(config('social.ai.api_key'))) {
            throw new SocialOrderExtractionException('AI order extraction is not configured.');
        }

        return DB::transaction(function () use ($conversation, $actor): SocialOrderExtraction {
            $locked = SocialConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            if ($locked->status !== ConversationStatus::Open || $locked->converted_order_id !== null) {
                throw new SocialOrderExtractionException('Suggestions can only be generated for an open, unconverted conversation.');
            }

            $messages = $locked->messages()
                ->where('direction', MessageDirection::Inbound)
                ->whereNotNull('body')
                ->latest('sent_at')
                ->limit(40)
                ->get(['id', 'external_id', 'updated_at'])
                ->sortBy('id')
                ->values();

            if ($messages->isEmpty()) {
                throw new SocialOrderExtractionException('There are no customer text messages to extract.');
            }

            $profile = SocialAiExtractionProfile::query()->where('active', true)->first();
            $instructionsHash = $this->prompt->hash($profile);
            $profileIdentity = $profile?->uuid ?? 'builtin:'.$this->prompt->version(null);
            $inputHash = hash('sha256', $messages->map(fn ($message): string => $message->id.':'.$message->external_id.':'.$message->updated_at?->getTimestamp())->implode('|').'|'.$profileIdentity.'|'.$instructionsHash.'|'.($profile?->model ?? config('social.ai.model')));
            $existing = $locked->orderExtractions()
                ->where('input_hash', $inputHash)
                ->whereIn('status', [
                    OrderExtractionStatus::Queued,
                    OrderExtractionStatus::Processing,
                    OrderExtractionStatus::Ready,
                ])->latest()->first();

            if ($existing) {
                return $existing;
            }

            $extraction = $locked->orderExtractions()->create([
                'status' => OrderExtractionStatus::Queued,
                'provider' => 'openai',
                'social_ai_extraction_profile_id' => $profile?->id,
                'model' => $profile?->model ?? (string) config('social.ai.model'),
                'prompt_version' => $this->prompt->version($profile),
                'instructions_hash' => $instructionsHash,
                'input_hash' => $inputHash,
                'message_count' => $messages->count(),
                'source_message_ids' => $messages->pluck('id')->all(),
                'requested_by' => $actor->id,
            ]);

            ExtractSocialOrderSuggestion::dispatch($extraction->id)->onQueue('integrations')->afterCommit();

            return $extraction;
        });
    }
}
