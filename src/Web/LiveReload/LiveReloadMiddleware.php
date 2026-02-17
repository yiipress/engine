<?php

declare(strict_types=1);

namespace App\Web\LiveReload;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class LiveReloadMiddleware implements MiddlewareInterface
{
    private const string SCRIPT = <<<'JS'
<script>
(function(){
    function connect() {
        var es = new EventSource("/_live-reload");
        es.addEventListener("reload", function() { location.reload(); });
        es.addEventListener("ping", function() { es.close(); connect(); });
        es.onerror = function() { es.close(); setTimeout(connect, 2000); };
    }
    connect();
})();
</script>
JS;

    public function __construct(
        private readonly StreamFactoryInterface $streamFactory,
        private readonly bool $enabled = true,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (!$this->enabled) {
            return $response;
        }

        $contentType = $response->getHeaderLine('Content-Type');
        if (!str_contains($contentType, 'text/html')) {
            return $response;
        }

        $body = (string) $response->getBody();
        $position = strripos($body, '</body>');

        if ($position === false) {
            return $response;
        }

        $modified = substr($body, 0, $position) . self::SCRIPT . "\n" . substr($body, $position);

        return $response->withBody($this->streamFactory->createStream($modified));
    }
}
