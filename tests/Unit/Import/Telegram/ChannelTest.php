<?php

namespace App\Tests\Unit\Import\Telegram;

use App\Import\Telegram\Channel;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ChannelTest extends TestCase
{
    public function testCorrectMessage(): void
    {
        $message = [
            'id' => 1,
            'type' => 'service',
            'date' => '2022-11-02T12:00:29',
            'date_unixtime' => '1667379629',
            'actor' => 'samdark blog ☕️ (Alexander Makarov)',
            'actor_id' => 'channel1698448697',
            'action' => 'create_channel',
            'title' => 'samdark_blog',
            'text' => '',
            'text_entities' => [],
        ];

        $channel = new Channel($message);
        $this->assertSame('samdark_blog', $channel->getTitle());
        $this->assertEquals(DateTimeImmutable::createFromTimestamp(1667379629), $channel->getDate());
    }

    public function testThrowsWhenTypeIsNotService(): void
    {
        $message = [
            'id' => 1,
            'type' => 'message',
            'date' => '2022-11-02T12:00:29',
            'date_unixtime' => '1667379629',
            'actor' => 'samdark blog ☕️ (Alexander Makarov)',
            'actor_id' => 'channel1698448697',
            'action' => 'create_channel',
            'title' => 'samdark_blog',
            'text' => '',
            'text_entities' => [],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Message type should be "service", "message" received.');

        new Channel($message);
    }

    public function testThrowsWhenActionIsNotCreateChannel(): void
    {
        $message = [
            'id' => 1,
            'type' => 'service',
            'date' => '2022-11-02T12:00:29',
            'date_unixtime' => '1667379629',
            'actor' => 'samdark blog ☕️ (Alexander Makarov)',
            'actor_id' => 'channel1698448697',
            'action' => 'edit_channel',
            'title' => 'samdark_blog',
            'text' => '',
            'text_entities' => [],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Message action should be "create_channel", "edit_channel" received.');

        new Channel($message);
    }
}
