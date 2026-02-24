<?php

namespace App\Import\Telegram;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class Channel
{
    public function __construct(
        private array $message
    ) {
        if ($message['type'] !== 'service') {
            throw new InvalidArgumentException(
                sprintf('Message type should be "service", "%s" received.', $this->message['type'])
            );
        }

        if ($message['action'] !== 'create_channel') {
            throw new InvalidArgumentException(
                sprintf('Message action should be "create_channel", "%s" received.', $this->message['action'])
            );
        }
    }

    public function getTitle(): string
    {
        return $this->message['title'];
    }

    public function getDate(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromTimestamp((int)$this->message['date_unixtime']);
    }
}
