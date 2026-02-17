<?php

declare(strict_types=1);

namespace App\Web\LiveReload;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class LiveReloadAction
{
    private const int POLL_INTERVAL_MICROSECONDS = 500_000;
    private const int MAX_DURATION_SECONDS = 30;

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private FileWatcher $fileWatcher,
        private SiteBuildRunner $buildRunner,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $body = $this->poll();

        return $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'text/event-stream')
            ->withHeader('Cache-Control', 'no-cache')
            ->withHeader('Connection', 'keep-alive')
            ->withHeader('X-Accel-Buffering', 'no')
            ->withBody($this->streamFactory->createStream($body));
    }

    private function poll(): string
    {
        $deadline = time() + self::MAX_DURATION_SECONDS;

        while (time() < $deadline) {
            if ($this->fileWatcher->hasChanges()) {
                $this->buildRunner->build();
                return "event: reload\ndata: changed\n\n";
            }

            usleep(self::POLL_INTERVAL_MICROSECONDS);
        }

        return "event: ping\ndata: timeout\n\n";
    }
}
