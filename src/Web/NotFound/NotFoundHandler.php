<?php

declare(strict_types=1);

namespace App\Web\NotFound;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Http\Status;

final readonly class NotFoundHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path = htmlspecialchars($request->getUri()->getPath(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head><meta charset="UTF-8"><title>404</title></head>
            <body>
            <h1>404</h1>
            <p>The page <strong>$path</strong> was not found.</p>
            </body>
            </html>
            HTML;

        return $this->responseFactory
            ->createResponse(Status::NOT_FOUND)
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withBody($this->streamFactory->createStream($html));
    }
}
