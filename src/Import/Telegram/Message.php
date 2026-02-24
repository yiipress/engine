<?php

namespace App\Import\Telegram;

use DateTimeImmutable;

/**
 * Telegram message.
 */
final class Message
{
    private bool $processed = false;

    /**
     * @param array $message Exported message data.
     * @param Channel|null $channel Channel.
     */
    public function __construct(
        private readonly array $message,
        private readonly ?Channel $channel
    ) {
    }

    public string $id {
        get {
            return $this->message['id'];
        }
    }

    public string $title {
        get {
            $this->ensureProcessed();
            return $this->title;
        }
    }
    public string $markdown {
        get {
            $this->ensureProcessed();
            return $this->markdown;
        }
    }

    public string $slug {
        get {
            $this->ensureProcessed();
            return $this->slug;
        }
    }

    public array $tags {
        get {
            $this->ensureProcessed();
            return $this->tags;
        }
    }

    public DateTimeImmutable $date {
        get {
            $time = $this->message['date_unixtime'] ?? time();
            return DateTimeImmutable::createFromTimestamp((int)$time);
        }
    }

    public DateTimeImmutable $edited {
        get {
            $time = $this->message['edited_unixtime'] ?? $this->message['date_unixtime'] ?? time();
            return DateTimeImmutable::createFromTimestamp((int)$time);
        }
    }

    public string $telegramLink {
        get {
            return "https://t.me/{$this->channel->getTitle()}/{$this->message['id']}";
        }
    }

    public ?string $photo {
        get {
            return $this->message['photo'] ?? null;
        }
    }

    public ?string $file {
        get {
            return $this->message['file'] ?? null;
        }
    }

    public ?string $forwardedFrom {
        get {
            return $this->message['forwarded_from'] ?? null;
        }
    }

    private function getSlugFromTitle(string $title): string
    {
        $slug = mb_strtolower($title);
        $slug = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', (string) $slug);
        $slug = trim((string) $slug, '-');

        if (mb_strlen($slug) > 80) {
            $slug = mb_substr($slug, 0, 80);
            $slug = rtrim($slug, '-');
        }

        if ($slug === '') {
            $slug = 'post';
        }

        return $slug;
    }

    private function ensureProcessed(): void
    {
        if ($this->processed) {
            return;
        }

        $markdown = $this->convertToMarkdown($this->message['text'], $this->message['text_entities']);

        $title = $this->extractTitle($markdown);
        $slug = $this->getSlugFromTitle($title);
        $markdown = $this->removeTitleFromMarkdown($markdown, $title);

        $this->markdown = $markdown;
        $this->title = $title;
        $this->slug = $slug;

        $tags = $this->extractHashtagsFromTextEntities($this->message['text_entities']);
        if (is_array($this->message['text'])) {
            $tags = $this->mergeHashtags($tags, $this->extractHashtagsFromTextArray($this->message['text']));
        }
        $this->tags = $tags;

        $this->processed = true;
    }

    /**
     * @param list<mixed> $textEntities
     * @return list<string>
     */
    private function extractHashtagsFromTextEntities(array $textEntities): array
    {
        $tags = [];
        foreach ($textEntities as $entity) {
            if ($entity['type'] !== 'hashtag') {
                continue;
            }
            $text = $entity['text'];
            if (!is_string($text) || $text === '') {
                continue;
            }
            $tag = ltrim($text, '#');
            $tag = mb_strtolower($tag);
            if ($tag !== '' && !in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    /**
     * @param list<mixed> $textArray
     * @return list<string>
     */
    private function extractHashtagsFromTextArray(array $textArray): array
    {
        $tags = [];
        foreach ($textArray as $part) {
            if (!is_array($part)) {
                continue;
            }
            if (($part['type'] ?? '') !== 'hashtag') {
                continue;
            }
            $text = $part['text'] ?? '';
            if (!is_string($text) || $text === '') {
                continue;
            }
            $tag = ltrim($text, '#');
            $tag = mb_strtolower($tag);
            if ($tag !== '' && !in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    /**
     * @param list<string> $first
     * @param list<string> $second
     * @return list<string>
     */
    private function mergeHashtags(array $first, array $second): array
    {
        foreach ($second as $tag) {
            if (!in_array($tag, $first, true)) {
                $first[] = $tag;
            }
        }

        return $first;
    }

    /**
     * @param string|list<array> $text
     * @param list<array> $textEntities
     */
    private function convertToMarkdown(string|array $text, array $textEntities): string
    {
        if (is_string($text) && $textEntities === []) {
            return $text;
        }

        if (is_array($text)) {
            return $this->convertTextArrayToMarkdown($text);
        }

        return $this->convertEntitiesOverText($text, $textEntities);
    }

    /**
     * @param list<array|string> $textArray
     */
    private function convertTextArrayToMarkdown(array $textArray): string
    {
        $result = '';
        foreach ($textArray as $part) {
            if (is_string($part)) {
                $result .= $part;
                // WTF?
                // TODO: why \n\n?
                continue;
            }

            $text = $part['text'];
            if (trim($text) === '') {
                $result .= $text;
                continue;
            }

            $result .= match ($part['type']) {
                'bold' => "**$text**",
                'italic' => "*$text*",
                'strikethrough' => "~~$text~~",
                'code' => "`$text`",
                'pre' => "\n```{$part['language']}\n$text\n```\n",
                'text_link' => "[$text]({$part['href']})",
                'link' => "[$text]({$this->ensureUrl($text)})",
                'email' => "[$text](mailto:$text)",
                'blockquote' => $this->blockQuoteToMarkdown($text),
                'mention' => "[$text]({$this->mentionToLink($text)})",
                default => $text,
            };
        }

        return trim($result);
    }

    private function blockQuoteToMarkdown(string $text): string
    {
        return implode(
            "\n",
            array_map(
                static fn ($line) => "> $line",
                explode("\n", $text)
            )
        );
    }

    private function mentionToLink(string $mention): string
    {
        $name = ltrim($mention, '@');
        return "https://t.me/$name";
    }

    private function ensureUrl(string $url): string
    {
        if (str_starts_with($url, 'http')) {
            return $url;
        }

        return 'https://' . $url;
    }

    /**
     * String text with entities (offset, length, formatting to apply).
     *
     * @param list<mixed> $entities
     */
    private function convertEntitiesOverText(string $text, array $entities): string
    {
        if ($entities === []) {
            return $text;
        }

        $chars = mb_str_split($text);
        $length = count($chars);

        $annotations = array_fill(0, $length, []);

        foreach ($entities as $entity) {
            $type = $entity['type'];
            $offset = (int) ($entity['offset'] ?? 0);
            $entityLength = (int) ($entity['length'] ?? 0);
            $href = $entity['href'] ?? '';
            $language = $entity['language'] ?? '';

            if ($type === 'hashtag') {
                for ($i = $offset; $i < $offset + $entityLength && $i < $length; $i++) {
                    // Keep hashtag text, don't remove it
                }
                continue;
            }

            for ($i = $offset; $i < $offset + $entityLength && $i < $length; $i++) {
                $annotations[$i][] = ['type' => $type, 'href' => $href, 'language' => $language, 'start' => $i === $offset, 'end' => $i === $offset + $entityLength - 1];
            }
        }

        $result = '';
        for ($i = 0; $i < $length; $i++) {
            foreach ($annotations[$i] as $annotation) {
                if ($annotation['start']) {
                    $result .= match ($annotation['type']) {
                        'bold' => '**',
                        'italic' => '*',
                        'strikethrough' => '~~',
                        'code' => '`',
                        'pre' => "\n```" . ($annotation['language'] ?? '') . "\n",
                        'text_link' => '[',
                        default => '',
                    };
                }
            }

            $result .= $chars[$i];

            foreach (array_reverse($annotations[$i]) as $annotation) {
                if ($annotation['end']) {
                    $result .= match ($annotation['type']) {
                        'bold' => '**',
                        'italic' => '*',
                        'strikethrough' => '~~',
                        'code' => '`',
                        'pre' => "\n```\n",
                        'text_link' => '](' . $annotation['href'] . ')',
                        default => '',
                    };
                }
            }
        }

        return trim($result);
    }

    private function extractTitle(string $markdown): string
    {
        $lines = explode("\n", $markdown);

        foreach ($lines as $line) {
            $title = trim($line);

            // Skip empty lines
            if ($title === '') {
                continue;
            }

            // Skip lines that contain only hashtags (tags)
            if (preg_match('/^(?:#\w+\s*)+$/', $title)) {
                continue;
            }

            $title = preg_replace('/^#{1,6}\s+/', '', $title);
            $title = preg_replace('/\*\*(.+?)\*\*/', '$1', (string) $title);
            $title = preg_replace('/\*(.+?)\*/', '$1', (string) $title);
            $title = preg_replace('/`(.+?)`/', '$1', (string) $title);
            $title = preg_replace('/\[([^]]+)]\([^)]+\)/', '$1', (string) $title);
            $title = trim((string) $title);

            if (mb_strlen($title) > 100) {
                $title = mb_substr($title, 0, 100);
                $lastSpace = mb_strrpos($title, ' ');
                if ($lastSpace !== false && $lastSpace > 50) {
                    $title = mb_substr($title, 0, $lastSpace);
                }
            }

            // Return the first valid title found
            if ($title !== '') {
                return $title;
            }
        }

        // Fallback title if no valid title found
        return 'Post ' . $this->id;
    }

    private function removeTitleFromMarkdown(string $markdown, string $title): string
    {
        if ($title === '') {
            return $markdown;
        }

        $lines = explode("\n", $markdown);
        if (empty($lines)) {
            return $markdown;
        }

        $firstLine = $lines[0];
        $firstLineTrimmed = trim($firstLine);

        // Remove Markdown formatting from first line to compare with title
        $firstLineClean = preg_replace('/^#{1,6}\s+/', '', $firstLineTrimmed);
        $firstLineClean = preg_replace('/\*\*(.+?)\*\*/', '$1', (string) $firstLineClean);
        $firstLineClean = preg_replace('/\*(.+?)\*/', '$1', (string) $firstLineClean);
        $firstLineClean = preg_replace('/`(.+?)`/', '$1', (string) $firstLineClean);
        $firstLineClean = preg_replace('/\[([^]]+)]\([^)]+\)/', '$1', (string) $firstLineClean);
        $firstLineClean = trim((string) $firstLineClean);

        if ($firstLineClean === $title) {
            // Remove the first line
            array_shift($lines);
            return implode("\n", $lines);
        }

        return $markdown;
    }
}
