<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Web\DevServer;

use YiiPress\Web\DevServer\DevHtmlMiddleware;
use HttpSoft\Message\Response;
use HttpSoft\Message\ServerRequest;
use HttpSoft\Message\StreamFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class DevHtmlMiddlewareTest extends TestCase
{
    public function testInjectsPreviewScriptsIntoFrameworkServedHtml(): void
    {
        $middleware = new DevHtmlMiddleware(new StreamFactory());

        $response = $middleware->process(
            new ServerRequest(),
            $this->createHandler('<html><body><p>Hello</p></body></html>', 'text/html'),
        );

        $body = (string) $response->getBody();
        self::assertStringContainsString('EventSource("/_live-reload")', $body);
        self::assertStringContainsString('fetch("/_open-source"', $body);
    }

    public function testSkipsNonHtmlResponses(): void
    {
        $middleware = new DevHtmlMiddleware(new StreamFactory());
        $json = '{"key": "value"}';

        $response = $middleware->process(
            new ServerRequest(),
            $this->createHandler($json, 'application/json'),
        );

        self::assertSame($json, (string) $response->getBody());
    }

    private function createHandler(string $body, string $contentType): RequestHandlerInterface
    {
        return new readonly class ($body, $contentType) implements RequestHandlerInterface {
            public function __construct(
                private string $body,
                private string $contentType,
            ) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $streamFactory = new StreamFactory();
                return new Response()
                    ->withHeader('Content-Type', $this->contentType)
                    ->withBody($streamFactory->createStream($this->body));
            }
        };
    }
}
