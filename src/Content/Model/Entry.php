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
        public string $summary,
        public string $permalink,
        public string $layout,
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
