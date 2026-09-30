<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Social\Adapters\MetaMessengerAdapter;
use App\Domain\Social\Enums\MessageType;
use PHPUnit\Framework\TestCase;

class MetaMessengerAdapterTest extends TestCase
{
    public function test_it_maps_page_message_and_ignores_echoes(): void
    {
        $adapter = new MetaMessengerAdapter;
        $messages = $adapter->messages([
            'object' => 'page',
            'entry' => [['messaging' => [
                ['sender' => ['id' => 'person'], 'recipient' => ['id' => 'page'], 'timestamp' => 1790755200000, 'message' => ['mid' => 'm1', 'text' => 'Hello']],
                ['sender' => ['id' => 'page'], 'recipient' => ['id' => 'person'], 'timestamp' => 1790755200000, 'message' => ['mid' => 'm2', 'text' => 'Reply', 'is_echo' => true]],
            ]]],
        ]);

        $this->assertCount(1, $messages);
        $this->assertSame('page', $messages[0]->channelExternalId);
        $this->assertSame('person', $messages[0]->contactExternalId);
        $this->assertSame(MessageType::Text, $messages[0]->type);
        $this->assertSame('Hello', $messages[0]->body);
    }
}
