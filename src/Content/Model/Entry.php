<?php

declare(strict_types=1);

namespace App\Content\Model;

use DateTimeImmutable;

final readonly class Entry
{
    /**
     * @param list<string> $tags
     * @param list<string> $categories
     * @param list<string> $authors
     * @param array<string, mixed> $extra
     */
    public function __construct(
        private string $filePath,
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
    ) {}

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

        $plain = self::stripMarkdown($body);
        if (mb_strlen($plain) <= self::SUMMARY_LENGTH) {
            return $plain;
        }

        $truncated = mb_substr($plain, 0, self::SUMMARY_LENGTH);
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false && $lastSpace > self::SUMMARY_LENGTH / 2) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return $truncated . '…';
    }

    private static function stripMarkdown(string $markdown): string
    {
        $text = preg_replace('/```.*?```/s', '', $markdown);
        $text = preg_replace('/^#{1,6}\s+/m', '', (string) $text);
        $text = preg_replace('/\*\*(.+?)\*\*/', '$1', (string) $text);
        $text = preg_replace('/\*(.+?)\*/', '$1', (string) $text);
        $text = preg_replace('/`(.+?)`/', '$1', (string) $text);
        $text = preg_replace('/!?\[([^\]]*)\]\([^)]+\)/', '$1', (string) $text);
        $text = preg_replace('/^\s*[-*+]\s+/m', '', (string) $text);
        $text = preg_replace('/^\s*\d+\.\s+/m', '', (string) $text);
        $text = preg_replace('/^>\s?/m', '', (string) $text);
        $text = preg_replace('/\n{2,}/', ' ', (string) $text);
        $text = preg_replace('/\n/', ' ', (string) $text);

        return trim((string) preg_replace('/\s+/', ' ', (string) $text));
    }

    public function body(): string
    {
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
            return $body === false ? '' : $body;
        } finally {
            fclose($handle);
        }
    }
}
