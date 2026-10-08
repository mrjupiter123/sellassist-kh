<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Social\Actions\CheckAiReleaseDegradation;
use Illuminate\Console\Command;

class MonitorAiProfileReleases extends Command
{
    protected $signature = 'social:ai:monitor-releases';

    protected $description = 'Assess the active AI profile release and notify administrators of degradation';

    public function handle(CheckAiReleaseDegradation $action): int
    {
        $result = $action->execute();

        if (! $result['release']) {
            $this->info('No active managed AI profile release is available to monitor.');

            return self::SUCCESS;
        }

        $this->info("Release {$result['release']->uuid}: {$result['status']}; notifications sent: {$result['notified']}.");

        return self::SUCCESS;
    }
}
