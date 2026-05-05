<?php

declare(strict_types=1);

namespace YiiPress\Web\DevServer;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class DevHtmlMiddleware implements MiddlewareInterface
{
    public function __construct(
        private StreamFactoryInterface $streamFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $contentType = $response->getHeaderLine('Content-Type');
        if (!str_contains($contentType, 'text/html')) {
            return $response;
        }

        return $response->withBody(
            $this->streamFactory->createStream(DevHtmlInjector::inject((string) $response->getBody())),
        );
    }
}
