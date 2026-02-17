<?php

declare(strict_types=1);

namespace App\Tests\Unit\Web\LiveReload;

use App\Web\LiveReload\LiveReloadMiddleware;
use HttpSoft\Message\Response;
use HttpSoft\Message\ServerRequest;
use HttpSoft\Message\StreamFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

final class LiveReloadMiddlewareTest extends TestCase
{
    public function testInjectsScriptBeforeClosingBodyTag(): void
    {
        $middleware = new LiveReloadMiddleware(new StreamFactory());
        $html = '<html><body><p>Hello</p></body></html>';

        $response = $middleware->process(
            new ServerRequest(),
            $this->createHandler($html, 'text/html'),
        );

        $body = (string) $response->getBody();
        assertStringContainsString('EventSource("/_live-reload")', $body);
        assertStringContainsString('</body>', $body);
    }

    public function testScriptAppearsBeforeClosingBodyTag(): void
    {
        $middleware = new LiveReloadMiddleware(new StreamFactory());
        $html = '<html><body><p>Hello</p></body></html>';

        $response = $middleware->process(
            new ServerRequest(),
            $this->createHandler($html, 'text/html'),
        );

        $body = (string) $response->getBody();
        $scriptPos = strpos($body, 'EventSource');
        $bodyClosePos = strpos($body, '</body>');
        assertSame(true, $scriptPos < $bodyClosePos);
    }

    public function testSkipsNonHtmlResponses(): void
    {
        $middleware = new LiveReloadMiddleware(new StreamFactory());
        $json = '{"key": "value"}';

        $response = $middleware->process(
            new ServerRequest(),
            $this->createHandler($json, 'application/json'),
        );

        assertSame($json, (string) $response->getBody());
    }

    public function testSkipsResponsesWithoutBodyTag(): void
    {
        $middleware = new LiveReloadMiddleware(new StreamFactory());
        $html = '<html><p>No body tag</p></html>';

        $response = $middleware->process(
            new ServerRequest(),
            $this->createHandler($html, 'text/html'),
        );

        assertSame($html, (string) $response->getBody());
    }

    public function testSkipsWhenDisabled(): void
    {
        $middleware = new LiveReloadMiddleware(new StreamFactory(), false);
        $html = '<html><body><p>Hello</p></body></html>';

        $response = $middleware->process(
            new ServerRequest(),
            $this->createHandler($html, 'text/html'),
        );

        assertStringNotContainsString('EventSource', (string) $response->getBody());
    }

    private function createHandler(string $body, string $contentType): RequestHandlerInterface
    {
        return new class ($body, $contentType) implements RequestHandlerInterface {
            public function __construct(
                private string $body,
                private string $contentType,
            ) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $streamFactory = new StreamFactory();
                return (new Response())
                    ->withHeader('Content-Type', $this->contentType)
                    ->withBody($streamFactory->createStream($this->body));
            }
        };
    }
}
