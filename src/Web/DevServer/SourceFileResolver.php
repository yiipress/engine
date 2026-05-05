<?php

declare(strict_types=1);

namespace YiiPress\Web\DevServer;

use YiiPress\Build\BuildManifest;
use Throwable;

use function is_file;
use function pathinfo;
use function realpath;
use function str_starts_with;
use function strtolower;
use function trim;
use function urldecode;

use const PATHINFO_EXTENSION;

final readonly class SourceFileResolver
{
    public function __construct(
        private string $manifestPath,
        private string $contentDir,
        private string $outputDir,
    ) {}

    public function resolve(string $urlPath): ?string
    {
        $outputFile = $this->resolveOutputFile($urlPath);
        if ($outputFile === null) {
            return null;
        }

        $manifest = new BuildManifest($this->manifestPath);
        try {
            $manifest->load();
        } catch (Throwable) {
            return null;
        }

        foreach ($manifest->entries() as $sourceFile => $entry) {
            if (strtolower(pathinfo($sourceFile, PATHINFO_EXTENSION)) !== 'md') {
                continue;
            }

            $sourceFile = $this->secureContentPath($sourceFile);
            if ($sourceFile === null) {
                continue;
            }

            foreach ($entry['outputs'] as $manifestOutputFile) {
                if (realpath($manifestOutputFile) === $outputFile) {
                    return $sourceFile;
                }
            }
        }

        return null;
    }

    private function resolveOutputFile(string $urlPath): ?string
    {
        $path = '/' . trim(urldecode($urlPath), '/');

        $candidate = $this->outputDir . $path;
        if (is_file($candidate)) {
            return $this->secureOutputPath($candidate);
        }

        $index = $this->outputDir . $path . '/index.html';
        if (is_file($index)) {
            return $this->secureOutputPath($index);
        }

        return null;
    }

    private function secureContentPath(string $filePath): ?string
    {
        $realPath = realpath($filePath);
        $realRoot = realpath($this->contentDir);

        if ($realPath === false || $realRoot === false) {
            return null;
        }

        if ($realPath !== $realRoot && !str_starts_with($realPath, $realRoot . '/')) {
            return null;
        }

        return $realPath;
    }

    private function secureOutputPath(string $filePath): ?string
    {
        $realPath = realpath($filePath);
        $realRoot = realpath($this->outputDir);

        if ($realPath === false || $realRoot === false) {
            return null;
        }

        if ($realPath !== $realRoot && !str_starts_with($realPath, $realRoot . '/')) {
            return null;
        }

        return $realPath;
    }
}
