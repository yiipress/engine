<?php

declare(strict_types=1);

namespace App\Content;

use App\Build\RelativePathHelper;

use function hash;

final readonly class CrossReferenceResolver
{
    private string $signature;

    /**
     * @param array<string, string> $fileToPermalink content-relative .md path => permalink URL
     */
    public function __construct(
        private array $fileToPermalink,
        private string $currentDir = '',
        private string $currentPermalink = '',
        string $signature = '',
    ) {
        if ($signature === '') {
            $fileToPermalink = $this->fileToPermalink;
            ksort($fileToPermalink);
            $signature = hash('xxh128', json_encode($fileToPermalink, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        }

        $this->signature = $signature;
    }

    public function withCurrentDir(string $dir): self
    {
        return new self($this->fileToPermalink, $dir, $this->currentPermalink, $this->signature);
    }

    public function withCurrentPermalink(string $permalink): self
    {
        return new self($this->fileToPermalink, $this->currentDir, $permalink, $this->signature);
    }

    public function resolve(string $markdown): string
    {
        return preg_replace_callback(
            '/\[([^\]]*)\]\(([^)#]+\.md)(#[^)]*)?\)/',
            function (array $matches): string {
                $text = $matches[1];
                $path = $matches[2];
                $fragment = $matches[3] ?? '';

                $resolved = $this->resolvePath($path);
                $permalink = $this->fileToPermalink[$resolved] ?? null;

                if ($permalink === null) {
                    return $matches[0];
                }

                if ($this->currentPermalink !== '') {
                    $rootPath = RelativePathHelper::rootPath($this->currentPermalink);
                    $permalink = RelativePathHelper::relativize($permalink, $rootPath);
                }

                return '[' . $text . '](' . $permalink . $fragment . ')';
            },
            $markdown,
        );
    }

    public function signature(): string
    {
        return $this->signature;
    }

    private function resolvePath(string $path): string
    {
        if (!str_starts_with($path, './') && !str_starts_with($path, '../')) {
            return $path;
        }

        $base = $this->currentDir !== '' ? $this->currentDir . '/' : '';
        $combined = $base . $path;

        $parts = [];
        foreach (explode('/', $combined) as $segment) {
            if ($segment === '.' || $segment === '') {
                continue;
            }
            if ($segment === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $segment;
        }

        return implode('/', $parts);
    }
}
