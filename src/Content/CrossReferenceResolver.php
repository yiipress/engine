<?php

declare(strict_types=1);

namespace App\Content;

use App\Build\RelativePathHelper;

final class CrossReferenceResolver
{
    /**
     * @param array<string, string> $fileToPermalink content-relative .md path => permalink URL
     */
    public function __construct(
        private readonly array $fileToPermalink,
        private readonly string $currentDir = '',
        private readonly string $currentPermalink = '',
    ) {}

    public function withCurrentDir(string $dir): self
    {
        return new self($this->fileToPermalink, $dir, $this->currentPermalink);
    }

    public function withCurrentPermalink(string $permalink): self
    {
        return new self($this->fileToPermalink, $this->currentDir, $permalink);
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
