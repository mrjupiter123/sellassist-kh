<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Social\Actions\IngestSocialMessage;
use App\Domain\Social\Adapters\MetaMessengerAdapter;
use App\Domain\Social\Enums\SocialWebhookStatus;
use App\Domain\Social\Models\SocialWebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessMetaWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $eventId) {}

    public function handle(MetaMessengerAdapter $adapter, IngestSocialMessage $ingest): void
    {
        $event = SocialWebhookEvent::query()->findOrFail($this->eventId);
        if ($event->status === SocialWebhookStatus::Processed) {
            return;
        }

        try {
            $processed = 0;
            foreach ($adapter->messages($event->payload) as $message) {
                if ($ingest->execute($message) !== null) {
                    $processed++;
                }
            }

            $event->update([
                'status' => $processed > 0 ? SocialWebhookStatus::Processed : SocialWebhookStatus::Ignored,
                'processed_messages' => $processed,
                'processed_at' => now(),
                'error' => null,
            ]);
        } catch (Throwable $exception) {
            $event->update([
                'status' => SocialWebhookStatus::Failed,
                'error' => $exception->getMessage(),
                'processed_at' => now(),
            ]);
            throw $exception;
        }
    }
}
