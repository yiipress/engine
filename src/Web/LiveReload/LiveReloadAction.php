<?php

declare(strict_types=1);

namespace App\Web\LiveReload;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class LiveReloadAction
{
    private const int RETRY_MILLISECONDS = 1_000;

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private FileWatcher $fileWatcher,
        private SiteBuildRunner $buildRunner,
        private int $waitTimeoutMilliseconds = 20_000,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $body = 'retry: ' . self::RETRY_MILLISECONDS . "\n";

        if ($this->fileWatcher->waitForChanges($this->waitTimeoutMilliseconds)) {
            $this->buildRunner->build();
            $body .= "event: reload\ndata: changed\n\n";
        } else {
            $body .= "event: ping\ndata: ok\n\n";
        }

        return $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'text/event-stream')
            ->withHeader('Cache-Control', 'no-cache')
            ->withHeader('Connection', 'keep-alive')
            ->withHeader('X-Accel-Buffering', 'no')
            ->withBody($this->streamFactory->createStream($body));
    }
}
