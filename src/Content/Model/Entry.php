<?php

declare(strict_types=1);

namespace App\Content\Model;

use DateTimeImmutable;

final class Entry
{
    /**
     * @param list<string> $tags
     * @param list<string> $inlineTags Tags that appear inline in the body (already rendered as links in content)
     * @param list<string> $categories
     * @param list<string> $authors
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string $filePath,
        public string $collection,
        public string $slug,
        public string $title,
        public ?DateTimeImmutable $date,
        public bool $draft,
        public array $tags,
        public array $categories,
        public array $authors,
        private string $summary,
        public string $permalink,
        public string $layout,
        public string $theme,
        public int $weight,
        public string $language,
        public string $redirectTo,
        public array $extra,
        private int $bodyOffset,
        private int $bodyLength,
        public string $image = '',
        public array $inlineTags = [],
    ) {}

    private ?string $bodyCache = null;

    public function sourceFilePath(): string
    {
        return $this->filePath;
    }

    private const int SUMMARY_LENGTH = 300;

    public function summary(): string
    {
        if ($this->summary !== '') {
            return $this->summary;
        }

        $body = $this->body();
        if ($body === '') {
            return '';
        }

        // Explicit cut marker takes priority over auto-truncation
        $cutPos = strpos($body, '[cut]');
        if ($cutPos !== false) {
            return trim(self::stripMarkdown(substr($body, 0, $cutPos)));
        }

        $plain = self::stripMarkdown($body);
        return self::truncate($plain, self::SUMMARY_LENGTH);
    }

    private static function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $maxLength);

        // Try sentence boundary in the last quarter of the limit
        $minPos = (int) ($maxLength * 0.75);
        $bestPos = -1;
        foreach (['. ', '! ', '? '] as $terminator) {
            $pos = mb_strrpos($truncated, $terminator);
            if ($pos !== false && $pos >= $minPos && $pos > $bestPos) {
                $bestPos = $pos;
            }
        }
        if ($bestPos !== -1) {
            return mb_substr($truncated, 0, $bestPos + 1);
        }

        // Fall back to word boundary
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false && $lastSpace > $maxLength / 2) {
            return mb_substr($truncated, 0, $lastSpace) . '…';
        }

        return $truncated . '…';
    }

    public static function stripMarkdown(string $markdown): string
    {
        // Fenced code blocks (with optional language identifier)
        $text = preg_replace('/```[^\n]*\n?.*?```/s', '', $markdown);
        // Indented code blocks (4 spaces or tab at line start)
        $text = preg_replace('/^(    |\t).+$/m', '', (string) $text);
        // HTML tags
        $text = strip_tags((string) $text);
        // Headings
        $text = preg_replace('/^#{1,6}\s+/m', '', (string) $text);
        // Horizontal rules
        $text = preg_replace('/^[-*_]{3,}\s*$/m', '', (string) $text);
        // Images (strip entirely, including alt text)
        $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', (string) $text);
        $text = preg_replace('/!\[[^\]]*\]\[[^\]]*\]/', '', (string) $text);
        // Links and reference-style links (keep link text)
        $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', (string) $text);
        $text = preg_replace('/\[([^\]]*)\]\[[^\]]*\]/', '$1', (string) $text);
        // Bold, italic, strikethrough (longest patterns first)
        $text = preg_replace('/\*\*\*(.+?)\*\*\*/s', '$1', (string) $text);
        $text = preg_replace('/___(.+?)___/s', '$1', (string) $text);
        $text = preg_replace('/\*\*(.+?)\*\*/s', '$1', (string) $text);
        $text = preg_replace('/__(.+?)__/s', '$1', (string) $text);
        $text = preg_replace('/\*(.+?)\*/s', '$1', (string) $text);
        $text = preg_replace('/(?<![_\w])_(.+?)_(?![_\w])/s', '$1', (string) $text);
        $text = preg_replace('/~~(.+?)~~/s', '$1', (string) $text);
        // Inline code (keep content)
        $text = preg_replace('/`([^`]+)`/', '$1', (string) $text);
        // Blockquote markers
        $text = preg_replace('/^>\s?/m', '', (string) $text);
        // List markers
        $text = preg_replace('/^\s*[-*+]\s+/m', '', (string) $text);
        $text = preg_replace('/^\s*\d+\.\s+/m', '', (string) $text);
        // Table rows (lines that start and end with |)
        $text = preg_replace('/^\|.+\|$/m', '', (string) $text);
        // [cut] markers
        $text = str_replace('[cut]', '', (string) $text);
        // Collapse whitespace
        $text = preg_replace('/\n{2,}/', ' ', (string) $text);
        $text = preg_replace('/\n/', ' ', (string) $text);

        return trim((string) preg_replace('/\s+/', ' ', (string) $text));
    }

    public function body(): string
    {
        if ($this->bodyCache !== null) {
            return $this->bodyCache;
        }

        if ($this->bodyLength === 0) {
            return '';
        }

        $handle = fopen($this->filePath, 'rb');
        if ($handle === false) {
            return '';
        }

        try {
            fseek($handle, $this->bodyOffset);
            $body = fread($handle, $this->bodyLength);
            $this->bodyCache = $body === false ? '' : $body;

            return $this->bodyCache;
        } finally {
            fclose($handle);
        }
    }
}
