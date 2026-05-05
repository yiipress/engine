<?php

declare(strict_types=1);

namespace YiiPress\Web\DevServer;

use YiiPress\RuntimePaths;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function getcwd;
use function hash;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function str_starts_with;
use function trim;

final readonly class OpenSourceAction
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private EditorLauncherInterface $editorLauncher,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $path = $this->requestPath($request);
        if ($path === null) {
            return $this->json(400, ['error' => 'Missing path.']);
        }

        $projectRoot = $this->serverPath($request, 'YIIPRESS_PROJECT_ROOT', getcwd() ?: '.');
        $contentDir = $this->serverPath($request, 'YIIPRESS_CONTENT_DIR', $projectRoot . '/content');
        $outputDir = $this->serverPath($request, 'YIIPRESS_OUTPUT_DIR', $projectRoot . '/output');
        $manifestPath = RuntimePaths::cachePath($projectRoot) . '/build-manifest-' . hash('xxh128', $outputDir) . '.json';

        $sourceFile = (new SourceFileResolver($manifestPath, $contentDir, $outputDir))->resolve($path);
        if ($sourceFile === null) {
            return $this->json(404, ['error' => 'Source file not found.']);
        }

        $editor = $this->editorLauncher->configuredEditorFromFile($contentDir . '/config.yaml');
        if (!$this->editorLauncher->open($sourceFile, $editor)) {
            return $this->json(500, ['error' => 'Editor could not be opened.']);
        }

        return $this->responseFactory->createResponse(204);
    }

    private function requestPath(ServerRequestInterface $request): ?string
    {
        $body = (string) $request->getBody();
        if ($body === '') {
            return null;
        }

        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['path']) || !is_string($data['path'])) {
            return null;
        }

        $path = trim($data['path']);

        return str_starts_with($path, '/') ? $path : null;
    }

    private function serverPath(ServerRequestInterface $request, string $key, string $default): string
    {
        $value = $request->getServerParams()[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * @param array<string, string> $payload
     */
    private function json(int $status, array $payload): ResponseInterface
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream($body));
    }
}
