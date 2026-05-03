<?php

declare(strict_types=1);

namespace App\Web\LiveReload;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class LiveReloadMiddleware implements MiddlewareInterface
{
    public const string SCRIPT = <<<'JS'
<script>
(function(){
    function connect() {
        var es = new EventSource("/_live-reload");
        es.addEventListener("reload", function() { es.close(); location.reload(); });
        es.addEventListener("ping", function() {});
        es.onerror = function() { es.close(); setTimeout(connect, 2000); };
    }
    connect();
})();
</script>
JS;

    public function __construct(
        private StreamFactoryInterface $streamFactory
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $contentType = $response->getHeaderLine('Content-Type');
        if (!str_contains($contentType, 'text/html')) {
            return $response;
        }

        $modified = self::injectScript((string) $response->getBody());

        return $response->withBody($this->streamFactory->createStream($modified));
    }

    public static function injectScript(string $body): string
    {
        $position = strripos($body, '</body>');

        if ($position === false) {
            return $body;
        }

        return substr($body, 0, $position) . self::SCRIPT . "\n" . substr($body, $position);
    }
}
