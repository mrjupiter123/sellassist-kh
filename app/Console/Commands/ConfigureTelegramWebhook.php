<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Social\Actions\ConfigureTelegramBot;
use DomainException;
use Illuminate\Console\Command;

class ConfigureTelegramWebhook extends Command
{
    protected $signature = 'social:telegram:configure
                            {--drop-pending : Discard Telegram updates waiting before this setup}';

    protected $description = 'Validate the Telegram bot and securely register the SellAssist webhook';

    public function handle(ConfigureTelegramBot $action): int
    {
        try {
            $result = $action->execute((bool) $this->option('drop-pending'));
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $channel = $result['channel'];
        $webhook = $result['webhook'];
        $this->info('Telegram bot configured successfully.');
        $this->line('Channel: '.$channel->name.' ('.$channel->external_id.')');
        $this->line('Webhook: '.(string) ($webhook['url'] ?? 'configured'));
        $this->line('Pending updates: '.(string) ($webhook['pending_update_count'] ?? 0));

        return self::SUCCESS;
    }
}
