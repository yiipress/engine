<?php

declare(strict_types=1);

namespace YiiPress\Import\Telegram;

use DateTimeImmutable;

/**
 * Telegram message.
 */
final class Message
{
    private bool $processed = false;

    /**
     * @param array<string, mixed> $message Exported message data.
     * @param Channel|null $channel Channel.
     */
    public function __construct(
        private readonly array $message,
        private readonly ?Channel $channel,
    ) {}

    public int $id {
        get {
            return $this->intField('id');
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

    /** @var list<string> */
    public array $tags {
        get {
            $this->ensureProcessed();
            return $this->tags;
        }
    }

    public DateTimeImmutable $date {
        get {
            return DateTimeImmutable::createFromTimestamp($this->intField('date_unixtime', time()));
        }
    }

    public DateTimeImmutable $edited {
        get {
            return DateTimeImmutable::createFromTimestamp(
                $this->intField('edited_unixtime', $this->intField('date_unixtime', time())),
            );
        }
    }

    public string $telegramLink {
        get {
            return $this->channel === null
                ? ''
                : "https://t.me/{$this->channel->getTitle()}/{$this->id}";
        }
    }

    public ?string $photo {
        get {
            return $this->nullableStringField('photo');
        }
    }

    public ?string $file {
        get {
            return $this->nullableStringField('file');
        }
    }

    public ?string $forwardedFrom {
        get {
            return $this->nullableStringField('forwarded_from');
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

        $text = $this->message['text'] ?? '';
        $text = is_string($text) || is_array($text) ? $text : '';
        $textEntities = $this->listField('text_entities');
        $markdown = $this->convertToMarkdown($text, $textEntities);

        $title = $this->extractTitle($markdown);
        $slug = $this->getSlugFromTitle($title);
        $markdown = $this->removeTitleFromMarkdown($markdown, $title);

        $this->markdown = $markdown;
        $this->title = $title;
        $this->slug = $slug;

        $tags = $this->extractHashtagsFromTextEntities($textEntities);
        if (is_array($text)) {
            $tags = $this->mergeHashtags($tags, $this->extractHashtagsFromTextArray(array_values($text)));
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
            if (!is_array($entity) || ($entity['type'] ?? null) !== 'hashtag') {
                continue;
            }
            $text = $entity['text'] ?? null;
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
     * @param string|array<array-key, mixed> $text
     * @param list<mixed> $textEntities
     */
    private function convertToMarkdown(string|array $text, array $textEntities): string
    {
        if (is_string($text) && $textEntities === []) {
            return $text;
        }

        if (is_array($text)) {
            return $this->convertTextArrayToMarkdown(array_values($text));
        }

        return $this->convertEntitiesOverText($text, $textEntities);
    }

    /**
     * @param list<mixed> $textArray
     */
    private function convertTextArrayToMarkdown(array $textArray): string
    {
        $result = '';
        foreach ($textArray as $part) {
            if (is_string($part)) {
                $result .= $part;
                continue;
            }

            if (!is_array($part)) {
                continue;
            }

            $text = $part['text'] ?? '';
            $text = is_string($text) ? $text : '';
            $type = $part['type'] ?? '';
            $type = is_string($type) ? $type : '';
            if (trim($text) === '') {
                $result .= $text;
                continue;
            }

            $isInlinePart = in_array($type, ['bold', 'italic', 'strikethrough'], true);
            $prefix = '';
            $suffix = '';
            if ($isInlinePart) {
                // Extract leading whitespace
                if (preg_match('~^(\s+|\R+)(.*)$~su', $text, $matches)) {
                    [,$prefix,$text] = $matches;
                }

                // Extract trailing whitespace from the remaining inner text
                if (preg_match('~^(.*?)(\s+|\R+)$~su', $text, $matches)) {
                    [,$text,$suffix] = $matches;
                }
            }

            $language = is_string($part['language'] ?? null) ? $part['language'] : '';
            $href = is_string($part['href'] ?? null) ? $part['href'] : '';
            $markdown = match ($type) {
                'bold' => "**$text**",
                'italic' => "*$text*",
                'strikethrough' => "~~$text~~",
                'code' => "`$text`",
                'pre' => "\n```$language\n$text\n```\n",
                'text_link' => "[$text]($href)",
                'link' => "[$text]({$this->ensureUrl($text)})",
                'email' => "[$text](mailto:$text)",
                'blockquote' => $this->blockQuoteToMarkdown($text),
                'mention' => "[$text]({$this->mentionToLink($text)})",
                default => $text,
            };

            if ($isInlinePart) {
                $markdown = $prefix . $markdown . $suffix;
            }

            $result .= $markdown;
        }

        return $result;
    }

    private function blockQuoteToMarkdown(string $text): string
    {
        return implode(
            "\n",
            array_map(
                static fn($line) => "> $line",
                explode("\n", $text),
            ),
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
            if (!is_array($entity)) {
                continue;
            }
            $type = is_string($entity['type'] ?? null) ? $entity['type'] : '';
            $offset = is_numeric($entity['offset'] ?? null) ? (int) $entity['offset'] : 0;
            $entityLength = is_numeric($entity['length'] ?? null) ? (int) $entity['length'] : 0;
            $href = is_string($entity['href'] ?? null) ? $entity['href'] : '';
            $language = is_string($entity['language'] ?? null) ? $entity['language'] : '';

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
                        'pre' => "\n```" . $annotation['language'] . "\n",
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

    private function intField(string $name, int $default = 0): int
    {
        $value = $this->message[$name] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    private function nullableStringField(string $name): ?string
    {
        $value = $this->message[$name] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @return list<mixed> */
    private function listField(string $name): array
    {
        $value = $this->message[$name] ?? [];

        return is_array($value) ? array_values($value) : [];
    }
}
