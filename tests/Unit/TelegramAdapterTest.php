<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Social\Adapters\TelegramAdapter;
use App\Domain\Social\Enums\MessageType;
use App\Domain\Social\Enums\SocialPlatform;
use App\Domain\Social\Models\SocialChannel;
use PHPUnit\Framework\TestCase;

class TelegramAdapterTest extends TestCase
{
    public function test_it_maps_private_messages_and_ignores_group_messages(): void
    {
        $adapter = new TelegramAdapter;
        $channel = new SocialChannel([
            'platform' => SocialPlatform::Telegram,
            'external_id' => '90001',
        ]);
        $payload = [
            'update_id' => 500,
            'message' => [
                'message_id' => 10,
                'date' => 1790985600,
                'chat' => ['id' => 70001, 'type' => 'private'],
                'from' => ['id' => 70001, 'first_name' => 'Sokha', 'username' => 'sokha_shop'],
                'text' => 'Two shirts please',
            ],
        ];

        $messages = $adapter->messages($payload, $channel);

        $this->assertCount(1, $messages);
        $this->assertSame(SocialPlatform::Telegram, $messages[0]->platform);
        $this->assertSame('90001', $messages[0]->channelExternalId);
        $this->assertSame('70001', $messages[0]->contactExternalId);
        $this->assertSame('Sokha (@sokha_shop)', $messages[0]->contactDisplayName);
        $this->assertSame(MessageType::Text, $messages[0]->type);

        $payload['message']['chat']['type'] = 'group';
        $this->assertSame([], $adapter->messages($payload, $channel));
    }
}
