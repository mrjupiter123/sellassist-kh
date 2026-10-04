<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Social\Enums\MessageDirection;
use App\Domain\Social\Enums\SocialMessageDeliveryStatus;
use App\Domain\Social\Exceptions\SocialMessageDeliveryException;
use App\Domain\Social\Models\SocialMessage;
use App\Domain\Social\Services\SocialMessageSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendSocialMessage implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public int $messageId) {}

    public function uniqueId(): string
    {
        return (string) $this->messageId;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("social-message-{$this->messageId}"))->expireAfter(60)];
    }

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(SocialMessageSender $sender): void
    {
        $message = SocialMessage::query()
            ->with(['conversation.channel', 'conversation.contact'])
            ->findOrFail($this->messageId);

        if ($message->direction !== MessageDirection::Outbound
            || $message->delivery_status === SocialMessageDeliveryStatus::Sent) {
            return;
        }

        $message->update([
            'delivery_status' => SocialMessageDeliveryStatus::Pending,
            'delivery_error' => null,
        ]);

        try {
            $result = $sender->send($message);
            $message->update([
                'external_id' => $result->externalId,
                'delivery_status' => SocialMessageDeliveryStatus::Sent,
                'delivery_error' => null,
                'raw_payload' => $result->payload,
                'sent_at' => $result->sentAt,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $safeMessage = $exception instanceof SocialMessageDeliveryException
                ? $exception->getMessage()
                : 'Social provider request failed. Review the application log for internal details.';
            $message->update([
                'delivery_status' => SocialMessageDeliveryStatus::Failed,
                'delivery_error' => $safeMessage,
            ]);

            throw new SocialMessageDeliveryException($safeMessage);
        }
    }
}
