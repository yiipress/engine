<?php

declare(strict_types=1);

namespace YiiPress\Build;

use YiiPress\Content\Model\Collection;
use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;
use YiiPress\Content\PermalinkResolver;

use function file_put_contents;
use function implode;
use function preg_replace;
use function str_replace;
use function trim;

final class LlmsTxtGenerator
{
    /**
     * @param array<string, Collection> $collections
     * @param array<string, list<Entry>> $entriesByCollection
     * @param list<Entry> $standalonePages
     */
    public function generate(
        SiteConfig $siteConfig,
        array $collections,
        array $entriesByCollection,
        string $outputDir,
        array $standalonePages = [],
        bool $noWrite = false,
    ): string {
        if (!$siteConfig->llmsTxt) {
            return '';
        }

        $lines = ['# ' . self::plainText($siteConfig->title)];
        $description = self::plainText($siteConfig->description);
        if ($description !== '') {
            $lines[] = '';
            $lines[] = $description;
        }

        foreach ($collections as $collectionName => $collection) {
            $items = $entriesByCollection[$collectionName] ?? [];
            if ($items === []) {
                continue;
            }

            $lines[] = '';
            $lines[] = '## ' . self::plainText($collection->title !== '' ? $collection->title : $collectionName);
            foreach ($items as $entry) {
                $lines[] = $this->entryLine(
                    $siteConfig,
                    $entry,
                    PermalinkResolver::resolve($entry, $collection, $siteConfig->i18n),
                );
            }
        }

        if ($standalonePages !== []) {
            $lines[] = '';
            $lines[] = '## Pages';
            foreach ($standalonePages as $page) {
                $basePermalink = $page->permalink !== '' ? $page->permalink : '/' . $page->slug . '/';
                $lines[] = $this->entryLine(
                    $siteConfig,
                    $page,
                    PermalinkResolver::applyLanguagePrefix($basePermalink, $page->language, $siteConfig->i18n),
                );
            }
        }

        $content = implode("\n", $lines) . "\n";
        if (!$noWrite) {
            file_put_contents($outputDir . '/llms.txt', $content);
        }

        return $content;
    }

    private function entryLine(SiteConfig $siteConfig, Entry $entry, string $permalink): string
    {
        $line = '- [' . self::linkText($entry->title) . '](' . UrlResolver::absoluteUrl($siteConfig, $permalink) . ')';
        $summary = self::plainText($entry->summary());
        if ($summary !== '') {
            $line .= ': ' . $summary;
        }

        return $line;
    }

    private static function plainText(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    private static function linkText(string $text): string
    {
        return str_replace(['\\', '[', ']'], ['\\\\', '\[', '\]'], self::plainText($text));
    }
}
