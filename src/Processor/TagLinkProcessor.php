<?php

declare(strict_types=1);

namespace YiiPress\Processor;

use YiiPress\Build\UrlResolver;
use YiiPress\Content\Model\Entry;

use function htmlspecialchars;
use function mb_strtolower;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function preg_split;
use function str_contains;
use function strip_tags;

final class TagLinkProcessor implements ContentProcessorInterface, RootPathAwareProcessorInterface
{
    private const HASHTAG_PATTERN = '/(?<!\w)#(\w+(?:-\w+)*)(?![\w-])/';
    private const PROTECTED_BLOCK_PATTERN = '/<pre[^>]*>.*?<\/pre>|<code[^>]*>.*?<\/code>|<a[^>]*>.*?<\/a>/is';
    private const HTML_SPLIT_PATTERN = '/(<pre[^>]*>.*?<\/pre>|<code[^>]*>.*?<\/code>|<a[^>]*>.*?<\/a>|<[^>]+>)/is';

    public function __construct(
        private string $rootPath = '/',
    ) {}

    public function applyRootPath(string $rootPath): void
    {
        $this->rootPath = $rootPath;
    }

    public function process(string $content, Entry $entry): string
    {
        if (!str_contains($content, '#')) {
            return $content;
        }

        if (!$this->hasConvertibleHashtag($content)) {
            return $content;
        }

        // Protect pre/code/a blocks (their content shouldn't have hashtags converted)
        // Then split by remaining HTML tags to exclude hashtags in attributes
        $parts = preg_split(self::HTML_SPLIT_PATTERN, $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return $content;
        }

        $result = '';
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                // Outside any HTML tag - convert hashtags
                $result .= $this->convertHashtags($part);
            } elseif (preg_match('/^<(pre|code|a)[^>]*>/i', $part)) {
                // Protected block (pre/code/a with content) - preserve unchanged
                $result .= $part;
            } elseif (preg_match('/^<\/(pre|code|a)>/i', $part)) {
                // Closing tags of protected blocks - already captured in the block
                $result .= $part;
            } else {
                // Other HTML tags (including attributes) - preserve unchanged
                $result .= $part;
            }
        }

        return $result;
    }

    private function convertHashtags(string $text): string
    {
        $result = preg_replace_callback(
            self::HASHTAG_PATTERN,
            function (array $matches): string {
                $tagDisplay = $matches[1];
                $tagUrl = mb_strtolower($tagDisplay);
                $url = UrlResolver::sitePath('/tags/' . $tagUrl . '/', $this->rootPath);
                return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="tag-link">#' . htmlspecialchars($tagDisplay, ENT_QUOTES, 'UTF-8') . '</a>';
            },
            $text
        );

        return $result ?? $text;
    }

    private function hasConvertibleHashtag(string $content): bool
    {
        $visibleContent = preg_replace(self::PROTECTED_BLOCK_PATTERN, '', $content);
        if ($visibleContent === null) {
            return true;
        }

        return preg_match(self::HASHTAG_PATTERN, strip_tags($visibleContent)) === 1;
    }
}
