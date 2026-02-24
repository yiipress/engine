<?php

declare(strict_types=1);

namespace App\Import;

use DateTimeImmutable;

use RuntimeException;

use function array_key_exists;
use function count;
use function in_array;
use function is_array;
use function is_string;

final class TelegramContentImporter implements ContentImporterInterface
{
    public function options(): array
    {
        return [
            new ImporterOption(
                name: 'directory',
                description: 'Path to the Telegram export directory containing result.json',
                required: true,
            ),
        ];
    }

    public function import(array $options, string $targetDirectory, string $collection): ImportResult
    {
        $sourceDirectory = $options['directory'] ?? '';
        if ($sourceDirectory === '' || !is_dir($sourceDirectory)) {
            return new ImportResult(
                totalMessages: 0,
                importedCount: 0,
                importedFiles: [],
                skippedFiles: [],
                warnings: ['directory option is required and must be a valid path'],
            );
        }

        $resultFile = $sourceDirectory . '/result.json';
        if (!is_file($resultFile)) {
            return new ImportResult(
                totalMessages: 0,
                importedCount: 0,
                importedFiles: [],
                skippedFiles: [],
                warnings: ["result.json not found in $sourceDirectory"],
            );
        }

        $json = file_get_contents($resultFile);
        if ($json === false) {
            return new ImportResult(
                totalMessages: 0,
                importedCount: 0,
                importedFiles: [],
                skippedFiles: [],
                warnings: ["Failed to read $resultFile"],
            );
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            return new ImportResult(
                totalMessages: 0,
                importedCount: 0,
                importedFiles: [],
                skippedFiles: [],
                warnings: ['Invalid JSON in result.json'],
            );
        }

        $messages = $this->extractMessages($data);
        $collectionDir = $targetDirectory . '/' . $collection;

        if (!is_dir($collectionDir) && !mkdir($collectionDir, 0o755, true) && !is_dir($collectionDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $collectionDir));
        }

        $assetsDir = $collectionDir . '/assets';

        $importedFiles = [];
        $skippedFiles = [];
        $warnings = [];
        $totalMessages = count($messages);

        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }

            $type = $message['type'] ?? '';
            if ($type !== 'message') {
                continue;
            }

            $text = $message['text'] ?? '';
            $textEntities = $message['text_entities'] ?? [];

            if ($text === '' && $textEntities === []) {
                $skippedFiles[] = 'message #' . ($message['id'] ?? '?') . ' (empty)';
                continue;
            }

            $dateString = $message['date'] ?? '';
            $date = $this->parseDate($dateString);
            if ($date === null) {
                $warnings[] = 'message #' . ($message['id'] ?? '?') . ': invalid date "' . $dateString . '"';
                $date = new DateTimeImmutable();
            }

            $tags = $this->extractHashtags($textEntities);
            if (is_array($text)) {
                $tags = $this->mergeHashtags($tags, $this->extractHashtagsFromTextArray($text));
            }
            $markdown = $this->convertToMarkdown($text, $textEntities);
            $title = $this->extractTitle($markdown);
            $markdown = $this->removeTitleFromMarkdown($markdown, $title);

            if ($title === '') {
                $title = 'Post ' . ($message['id'] ?? '?');
            }

            $slug = $this->slugify($title);
            $datePrefix = $date->format('Y-m-d');

            $filename = $datePrefix . '-' . $slug . '.md';
            $filePath = $collectionDir . '/' . $filename;

            $photo = $message['photo'] ?? '';
            $mediaPath = '';
            if (is_string($photo) && $photo !== '') {
                $mediaPath = $this->copyMedia($sourceDirectory, $photo, $assetsDir);
            }

            $file = $message['file'] ?? '';
            if ($mediaPath === '' && is_string($file) && $file !== '') {
                $mediaPath = $this->copyMedia($sourceDirectory, $file, $assetsDir);
            }

            $content = $this->buildMarkdownFile($title, $date, $tags, $markdown, $mediaPath, $collection);
            file_put_contents($filePath, $content);
            $importedFiles[] = $filePath;
        }

        $this->ensureCollectionConfig($collectionDir, $collection);

        return new ImportResult(
            totalMessages: $totalMessages,
            importedCount: count($importedFiles),
            importedFiles: $importedFiles,
            skippedFiles: $skippedFiles,
            warnings: $warnings,
        );
    }

    public function name(): string
    {
        return 'telegram';
    }

    /**
     * @param array<string, mixed> $data
     * @return list<mixed>
     */
    private function extractMessages(array $data): array
    {
        if (array_key_exists('messages', $data)) {
            return is_array($data['messages']) ? $data['messages'] : [];
        }

        if (array_key_exists('chats', $data) && is_array($data['chats']) && array_key_exists(
                'list',
                $data['chats']
            ) && is_array($data['chats']['list'])) {
                foreach ($data['chats']['list'] as $chat) {
                    if (is_array($chat) && array_key_exists('messages', $chat) && is_array($chat['messages'])) {
                        return $chat['messages'];
                    }
                }
            }

        return [];
    }

    private function parseDate(string $dateString): ?DateTimeImmutable
    {
        if ($dateString === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $dateString);
        if ($date !== false) {
            return $date;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateString);
        if ($date !== false) {
            return $date;
        }

        return null;
    }

    /**
     * @param list<mixed> $textEntities
     * @return list<string>
     */
    private function extractHashtags(array $textEntities): array
    {
        $tags = [];
        foreach ($textEntities as $entity) {
            if (!is_array($entity)) {
                continue;
            }
            if (($entity['type'] ?? '') !== 'hashtag') {
                continue;
            }
            $text = $entity['text'] ?? '';
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
     * @param string|list<mixed> $text
     * @param list<mixed> $textEntities
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
     * @param list<mixed> $textArray
     */
    private function convertTextArrayToMarkdown(array $textArray): string
    {
        $result = '';
        $i = 0;
        $count = count($textArray);

        while ($i < $count) {
            $part = $textArray[$i];

            if (is_string($part)) {
                // Check if this string contains list items and convert them in place
                $convertedText = $this->convertListItemsInText($part);
                
                // Check if this is a list item followed by a text_link
                $nextPart = $textArray[$i + 1] ?? null;
                if (is_array($nextPart) && ($nextPart['type'] ?? '') === 'text_link' && 
                    preg_match('/^[^\w\s]/u', trim($convertedText))) {
                    // This is a list item followed by a link, format it properly
                    $result .= '- ' . trim($convertedText) . '[' . $nextPart['text'] . '](' . $nextPart['href'] . ')';
                    $i += 2; // Skip both the text and the link
                    continue;
                }
                
                $result .= $convertedText;
                $i++;
                continue;
            }

            if (!is_array($part)) {
                $i++;
                continue;
            }

            $type = $part['type'] ?? 'plain';
            $partText = $part['text'] ?? '';
            if (!is_string($partText)) {
                $i++;
                continue;
            }

            // Check if this could be the start of a list
            if ($type === 'custom_emoji') {
                $listItems = $this->detectConsecutiveListItems($textArray, $i);

                if (count($listItems) >= 1) {
                    // We have list items, format as a proper markdown list
                    $listText = '';
                    foreach ($listItems as $index => $item) {
                        $listText .= '- ' . $item['emoji'] . $item['text'];
                        if ($index < count($listItems) - 1) {
                            $listText .= "\n";
                        }
                    }
                    $result .= "\n" . $listText . "\n";
                    $i += count($listItems) * 2; // Skip past all the list items
                    continue;
                }
            }

            // Handle text_link followed by plain text
            if ($type === 'text_link') {
                $nextPart = $textArray[$i + 1] ?? null;
                if (is_string($nextPart)) {
                    // Combine text_link with following plain text
                    $result .= '[' . $partText . '](' . ($part['href'] ?? '') . ')' . $nextPart;
                    $i += 2; // Skip both the link and the text
                    continue;
                }
            }

            // Handle single items or non-list patterns normally
            $result .= match ($type) {
                'bold' => '**' . $partText . '**',
                'italic' => '*' . $partText . '*',
                'strikethrough' => '~~' . $partText . '~~',
                'code' => '`' . $partText . '`',
                'pre' => "\n```" . ($part['language'] ?? '') . "\n" . $partText . "\n```\n",
                'text_link' => '[' . $partText . '](' . ($part['href'] ?? '') . ')',
                'link' => '[' . $partText . '](' . $partText . ')',
                'hashtag' => $partText,
                'mention' => $partText,
                'email' => '[' . $partText . '](mailto:' . $partText . ')',
                'phone' => $partText,
                'underline' => $partText,
                'spoiler' => $partText,
                'custom_emoji' => $partText,
                default => $partText,
            };
            $i++;
        }

        return trim($result);
    }

    /**
     * Detect consecutive emoji + text patterns that form a list
     * @param list<mixed> $textArray
     * @return list<array{emoji: string, text: string}>
     */
    private function detectConsecutiveListItems(array $textArray, int $startIndex): array
    {
        $listItems = [];
        $i = $startIndex;
        $count = count($textArray);

        while ($i < $count) {
            $emojiPart = $textArray[$i] ?? null;
            $textPart = $textArray[$i + 1] ?? null;

            // Check for simple pattern: emoji followed by text
            if (
                is_array($emojiPart) &&
                ($emojiPart['type'] ?? '') === 'custom_emoji' &&
                is_string($emojiPart['text'] ?? '') &&
                is_string($textPart)
            ) {
                $listItems[] = [
                    'emoji' => $emojiPart['text'],
                    'text' => rtrim($textPart)
                ];
                $i += 2;
                continue;
            }

            // Check for complex pattern: emoji + space + bold + text ending with bullet
            $spacePart = $textPart;
            $boldPart = $textArray[$i + 2] ?? null;
            $descPart = $textArray[$i + 3] ?? null;

            if (
                is_array($emojiPart) &&
                ($emojiPart['type'] ?? '') === 'custom_emoji' &&
                is_string($emojiPart['text'] ?? '') &&
                $spacePart === ' ' &&
                is_array($boldPart) &&
                ($boldPart['type'] ?? '') === 'bold' &&
                is_string($boldPart['text'] ?? '') &&
                is_string($descPart) &&
                preg_match('/^ — .*\n•\s/u', $descPart) === 1
            ) {
                // Remove the bullet and trailing space from the description
                $cleanDesc = preg_replace('/\n•\s$/u', '', $descPart);

                $listItems[] = [
                    'emoji' => $emojiPart['text'],
                    'text' => ' **' . $boldPart['text'] . '**' . $cleanDesc
                ];
                $i += 4;
                continue;
            }

            // Check for last item: emoji + space + bold + text ending with newline (no bullet)
            if (
                is_array($emojiPart) &&
                ($emojiPart['type'] ?? '') === 'custom_emoji' &&
                is_string($emojiPart['text'] ?? '') &&
                $spacePart === ' ' &&
                is_array($boldPart) &&
                ($boldPart['type'] ?? '') === 'bold' &&
                is_string($boldPart['text'] ?? '') &&
                is_string($descPart) &&
                preg_match('/^ — .*\n$/u', $descPart) === 1
            ) {
                $listItems[] = [
                    'emoji' => $emojiPart['text'],
                    'text' => ' **' . $boldPart['text'] . '**' . rtrim($descPart)
                ];
                $i += 4;
                continue;
            }

            break; // Pattern broken, stop detecting
        }

        return $listItems;
    }

    /**
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
            $type = $entity['type'] ?? 'plain';
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
            foreach ($annotations[$i] as $ann) {
                if ($ann['start']) {
                    $result .= match ($ann['type']) {
                        'bold' => '**',
                        'italic' => '*',
                        'strikethrough' => '~~',
                        'code' => '`',
                        'pre' => "\n```" . ($ann['language'] ?? '') . "\n",
                        'text_link' => '[',
                        default => '',
                    };
                }
            }

            $result .= $chars[$i];

            foreach (array_reverse($annotations[$i]) as $ann) {
                if ($ann['end']) {
                    $result .= match ($ann['type']) {
                        'bold' => '**',
                        'italic' => '*',
                        'strikethrough' => '~~',
                        'code' => '`',
                        'pre' => "\n```\n",
                        'text_link' => '](' . $ann['href'] . ')',
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

            return $title;
        }

        return '';
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

        // Remove markdown formatting from first line to compare with title
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

    /**
     * @param list<string> $tags
     */
    private function buildMarkdownFile(
        string $title,
        DateTimeImmutable $date,
        array $tags,
        string $body,
        string $mediaPath,
        string $collection,
    ): string {
        $frontMatter = "---\n";
        $frontMatter .= 'title: ' . $this->yamlEscape($title) . "\n";
        $frontMatter .= 'date: ' . $date->format('Y-m-d H:i:s') . "\n";

        if ($tags !== []) {
            $frontMatter .= "tags:\n";
            foreach ($tags as $tag) {
                $frontMatter .= '  - ' . $tag . "\n";
            }
        }

        $frontMatter .= "---\n\n";

        $content = $frontMatter;

        if ($mediaPath !== '') {
            $content .= '![](/' . $collection . '/assets/' . basename($mediaPath) . ")\n\n";
        }

        $content .= $body . "\n";

        return $content;
    }

    private function copyMedia(string $sourceDirectory, string $relativePath, string $assetsDir): string
    {
        $sourcePath = $sourceDirectory . '/' . $relativePath;
        if (!is_file($sourcePath)) {
            return '';
        }

        if (!is_dir($assetsDir) && !mkdir($assetsDir, 0o755, true) && !is_dir($assetsDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $assetsDir));
        }


        $info = pathinfo($relativePath);
        $targetPath = $assetsDir . '/' . ($info['filename'] ?? 'file') . '.' . ($info['extension'] ?? '');

        copy($sourcePath, $targetPath);

        return $targetPath;
    }

    private function ensureCollectionConfig(string $collectionDir, string $collection): void
    {
        $configPath = $collectionDir . '/_collection.yaml';
        if (is_file($configPath)) {
            return;
        }

        $config = 'title: ' . ucfirst($collection) . "\n";
        $config .= "sort_by: date\n";
        $config .= "sort_order: desc\n";
        $config .= "entries_per_page: 10\n";
        $config .= "feed: true\n";

        file_put_contents($configPath, $config);
    }

    /**
     * Detect list items in plain text strings
     * @return list<string>
     */
    private function detectListItemsInText(string $text): array
    {
        $listItems = [];
        
        // Split by newlines and check each line
        $lines = explode("\n", $text);
        
        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            
            // Skip empty lines
            if ($trimmedLine === '') {
                continue;
            }
            
            // Simple check: if line starts with non-alphanumeric character and has content after
            $firstChar = mb_substr($trimmedLine, 0, 1);
            
            // Check if first character is not a letter or number (i.e., it's a symbol/emoji)
            if (!preg_match('/^[A-Za-zА-Яа-я0-9]/u', $firstChar) && mb_strlen($trimmedLine) > 1) {
                // Convert to markdown list format
                $listItems[] = '- ' . $trimmedLine;
            }
        }
        
        return $listItems;
    }

    /**
     * Convert list items in text while preserving original structure
     */
    private function convertListItemsInText(string $text): string
    {
        $lines = explode("\n", $text);
        $convertedLines = [];
        
        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            
            // Skip empty lines
            if ($trimmedLine === '') {
                $convertedLines[] = $line;
                continue;
            }
            
            // Simple check: if line starts with non-alphanumeric character and has content after
            $firstChar = mb_substr($trimmedLine, 0, 1);
            
            // Check if first character is not a letter or number (i.e., it's a symbol/emoji)
            // But don't convert if the line ends with space (might be followed by other entities)
            if (!preg_match('/^[A-Za-zА-Яа-я0-9]/u', $firstChar) && 
                mb_strlen($trimmedLine) > 1 && 
                !str_ends_with($line, ' ')) {
                // Convert to markdown list format
                $convertedLines[] = '- ' . $trimmedLine;
            } else {
                // Keep original line
                $convertedLines[] = $line;
            }
        }
        
        return implode("\n", $convertedLines);
    }

    private function slugify(string $title): string
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

    private function yamlEscape(string $value): string
    {
        if (preg_match('/[:#\[\]{}|>&*!,\'"%@`]/', $value) === 1) {
            return '"' . addcslashes($value, '"\\') . '"';
        }

        return $value;
    }
}
