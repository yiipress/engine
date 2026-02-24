<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Author;
use App\Content\Model\Entry;
use App\Content\Model\SiteConfig;

use function dirname;
use function strlen;

final class BuildDiagnostics
{
    /** @var list<string> */
    private array $warnings = [];

    /**
     * @param array<string, string> $fileToPermalink content-relative .md path => permalink
     * @param array<string, Author> $authors
     */
    public function __construct(
        private readonly string $contentDir,
        private readonly array $fileToPermalink,
        private readonly SiteConfig $siteConfig,
        private readonly array $authors,
    ) {}

    public function check(Entry $entry): void
    {
        $this->checkFrontMatter($entry);
        $this->checkLinks($entry);
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    private function checkFrontMatter(Entry $entry): void
    {
        $source = $this->relativeSource($entry);

        if ($entry->title === '') {
            $this->warnings[] = "$source: missing title";
        }

        foreach ($entry->authors as $authorSlug) {
            if (!isset($this->authors[$authorSlug]) && $authorSlug !== $this->siteConfig->defaultAuthor) {
                $this->warnings[] = "$source: unknown author \"$authorSlug\"";
            }
        }

        foreach ($entry->tags as $tag) {
            if (trim($tag) === '') {
                $this->warnings[] = "$source: empty tag value";
            }
        }

        foreach ($entry->categories as $category) {
            if (trim($category) === '') {
                $this->warnings[] = "$source: empty category value";
            }
        }
    }

    private function checkLinks(Entry $entry): void
    {
        $body = $this->stripCodeBlocks($entry->body());
        if ($body === '') {
            return;
        }

        $source = $this->relativeSource($entry);
        $entryDir = $this->entryContentDir($entry);

        preg_match_all('/\[([^]]*)]\(([^)]+)\)/', $body, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $path = $match[2];

            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                continue;
            }

            $resolved = $this->resolveLinkPath($path, $entryDir);

            // check images
            if (str_starts_with($path, '/')) {
                $absolute = $this->contentDir . $path;
            } else {
                $absolute = $entryDir . '/' . $path;
            }

            $real = realpath($absolute);
            if ($real !== false && is_file($real)) {
                continue;
            }

            // check content
            if (isset($this->fileToPermalink[$resolved])) {
                continue;
            }

            $this->warnings[] = "$source: broken link to \"$path\"";
        }
    }

    private function stripCodeBlocks(string $body): string
    {
        $body = preg_replace('/````.*?````/s', '', $body);
        $body = preg_replace('/```.*?```/s', '', (string) $body);
        return preg_replace('/`[^`]+`/', '', (string) $body);
    }

    private function relativeSource(Entry $entry): string
    {
        return substr($entry->sourceFilePath(), strlen($this->contentDir) + 1);
    }

    private function entryContentDir(Entry $entry): string
    {
        $relative = $this->relativeSource($entry);
        $dir = dirname($relative);
        return $dir === '.' ? '' : $dir;
    }

    private function resolveLinkPath(string $path, string $currentDir): string
    {
        if (!str_starts_with($path, './') && !str_starts_with($path, '../')) {
            return $path;
        }

        $base = $currentDir !== '' ? $currentDir . '/' : '';
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
