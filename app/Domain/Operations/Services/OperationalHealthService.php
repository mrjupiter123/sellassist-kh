<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Domain\Delivery\Models\DeliveryIntegrationLog;
use App\Domain\Delivery\Models\DeliveryWebhookEvent;
use App\Domain\Social\Enums\SocialWebhookStatus;
use App\Domain\Social\Models\SocialWebhookEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class OperationalHealthService
{
    /** @return array<string, mixed> */
    public function summary(): array
    {
        $oldestPending = DB::table('jobs')->min('created_at');

        return [
            'pending_jobs' => DB::table('jobs')->count(),
            'oldest_pending_minutes' => $oldestPending === null
                ? null
                : max(0, (int) floor((now()->timestamp - (int) $oldestPending) / 60)),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'delivery_failures_24h' => DeliveryIntegrationLog::query()
                ->where('status', 'failed')
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'delivery_webhooks_waiting' => DeliveryWebhookEvent::query()
                ->whereIn('status', ['received', 'failed'])
                ->count(),
            'social_webhooks_waiting' => SocialWebhookEvent::query()
                ->whereIn('status', [SocialWebhookStatus::Received->value, SocialWebhookStatus::Failed->value])
                ->count(),
            'last_delivery_success' => DeliveryIntegrationLog::query()
                ->where('status', 'succeeded')
                ->latest()
                ->value('created_at'),
            'last_social_success' => SocialWebhookEvent::query()
                ->where('status', SocialWebhookStatus::Processed->value)
                ->latest('processed_at')
                ->value('processed_at'),
        ];
    }

    public function failedJobs(): LengthAwarePaginator
    {
        return DB::table('failed_jobs')
            ->select(['uuid', 'connection', 'queue', 'exception', 'failed_at'])
            ->latest('failed_at')
            ->paginate(20);
    }
}
