<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use DomainException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

final class RetryFailedJob
{
    public function execute(string $uuid): void
    {
        $exists = DB::table('failed_jobs')->where('uuid', $uuid)->exists();
        if (! $exists) {
            throw new DomainException('The failed job no longer exists.');
        }

        $exitCode = Artisan::call('queue:retry', ['id' => [$uuid]]);
        if ($exitCode !== 0) {
            throw new DomainException('The failed job could not be retried.');
        }
    }
}
