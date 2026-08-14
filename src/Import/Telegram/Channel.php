<?php

declare(strict_types=1);

namespace YiiPress\Import\Telegram;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class Channel
{
    private string $title;
    private int $timestamp;

    /** @param array<string, mixed> $message */
    public function __construct(array $message)
    {
        $type = $message['type'] ?? null;
        if ($type !== 'service') {
            throw new InvalidArgumentException(
                sprintf('Message type should be "service", "%s" received.', is_scalar($type) ? (string) $type : get_debug_type($type)),
            );
        }

        $action = $message['action'] ?? null;
        if ($action !== 'create_channel') {
            throw new InvalidArgumentException(
                sprintf('Message action should be "create_channel", "%s" received.', is_scalar($action) ? (string) $action : get_debug_type($action)),
            );
        }

        $title = $message['title'] ?? null;
        $timestamp = $message['date_unixtime'] ?? null;
        if (!is_string($title) || !is_numeric($timestamp)) {
            throw new InvalidArgumentException('Channel title and date_unixtime must be present and valid.');
        }

        $this->title = $title;
        $this->timestamp = (int) $timestamp;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDate(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromTimestamp($this->timestamp);
    }
}
