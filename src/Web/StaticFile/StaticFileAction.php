<?php

declare(strict_types=1);

namespace App\Web\StaticFile;

use App\Web\LiveReload\SiteBuildRunner;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class StaticFileAction
{
    private const array MIME_TYPES = [
        'html' => 'text/html; charset=utf-8',
        'htm' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'xml' => 'application/xml; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'txt' => 'text/plain; charset=utf-8',
    ];

    private bool $buildAttempted = false;

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $documentRoot,
        private readonly SiteBuildRunner $buildRunner,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $this->ensureBuilt();

        $path = $request->getUri()->getPath();
        $filePath = $this->resolveFilePath($path);

        if ($filePath === null || !is_file($filePath)) {
            return $this->notFound();
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeType = self::MIME_TYPES[$extension] ?? 'application/octet-stream';

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return $this->notFound();
        }

        return $this->responseFactory->createResponse()
            ->withHeader('Content-Type', $mimeType)
            ->withBody($this->streamFactory->createStream($contents));
    }

    private function resolveFilePath(string $path): ?string
    {
        $path = '/' . trim($path, '/');

        $candidate = $this->documentRoot . $path;

        if (is_file($candidate)) {
            return $this->securePath($candidate);
        }

        if (is_dir($candidate)) {
            $index = rtrim($candidate, '/') . '/index.html';
            if (is_file($index)) {
                return $this->securePath($index);
            }
        }

        return null;
    }

    private function securePath(string $filePath): ?string
    {
        $realPath = realpath($filePath);
        if ($realPath === false) {
            return null;
        }

        $realRoot = realpath($this->documentRoot);
        if ($realRoot === false) {
            return null;
        }

        if ($realPath !== $realRoot && !str_starts_with($realPath, $realRoot . '/')) {
            return null;
        }

        return $realPath;
    }

    private function ensureBuilt(): void
    {
        if ($this->buildAttempted) {
            return;
        }

        $this->buildAttempted = true;

        if (!is_dir($this->documentRoot) || $this->isEmptyDirectory($this->documentRoot)) {
            $this->buildRunner->build();
        }
    }

    private function isEmptyDirectory(string $path): bool
    {
        $handle = opendir($path);
        if ($handle === false) {
            return true;
        }

        while (($entry = readdir($handle)) !== false) {
            if ($entry !== '.' && $entry !== '..') {
                closedir($handle);
                return false;
            }
        }

        closedir($handle);
        return true;
    }

    private function notFound(): ResponseInterface
    {
        return $this->responseFactory->createResponse(404)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($this->streamFactory->createStream(
                '<!DOCTYPE html><html><body><h1>404 Not Found</h1></body></html>'
            ));
    }
}
