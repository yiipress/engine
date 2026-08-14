<?php

declare(strict_types=1);

namespace YiiPress\Content\Model;

final readonly class Author
{
    /**
     * @param int<0, max> $bodyOffset
     * @param int<0, max> $bodyLength
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $email,
        public string $url,
        public string $avatar,
        private int $bodyOffset,
        private int $bodyLength,
        private string $filePath,
    ) {}

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
