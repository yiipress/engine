<?php

declare(strict_types=1);

namespace App\Import;

use DateTimeImmutable;

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

        if (!is_dir($collectionDir)) {
            mkdir($collectionDir, 0o755, true);
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

            if ($title === '') {
                $title = 'Post ' . ($message['id'] ?? '?');
            }

            $slug = $this->slugify($title);
            $datePrefix = $date->format('Y-m-d');
            $filename = $datePrefix . '-' . $slug . '.md';
            $filePath = $collectionDir . '/' . $filename;

            $counter = 1;
            while (is_file($filePath)) {
                $filename = $datePrefix . '-' . $slug . '-' . $counter . '.md';
                $filePath = $collectionDir . '/' . $filename;
                $counter++;
            }

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
        foreach ($textArray as $part) {
            if (is_string($part)) {
                $result .= $part;
                continue;
            }
            if (!is_array($part)) {
                continue;
            }
            $type = $part['type'] ?? 'plain';
            $partText = $part['text'] ?? '';
            if (!is_string($partText)) {
                continue;
            }

            $result .= match ($type) {
                'bold' => '**' . $partText . '**',
                'italic' => '*' . $partText . '*',
                'strikethrough' => '~~' . $partText . '~~',
                'code' => '`' . $partText . '`',
                'pre' => "\n```\n" . $partText . "\n```\n",
                'text_link' => '[' . $partText . '](' . ($part['href'] ?? '') . ')',
                'link' => '[' . $partText . '](' . $partText . ')',
                'hashtag' => '',
                'mention' => $partText,
                'email' => '[' . $partText . '](mailto:' . $partText . ')',
                'phone' => $partText,
                'underline' => $partText,
                'spoiler' => $partText,
                default => $partText,
            };
        }

        return trim($result);
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

            if ($type === 'hashtag') {
                for ($i = $offset; $i < $offset + $entityLength && $i < $length; $i++) {
                    $chars[$i] = '';
                }
                continue;
            }

            for ($i = $offset; $i < $offset + $entityLength && $i < $length; $i++) {
                $annotations[$i][] = ['type' => $type, 'href' => $href, 'start' => $i === $offset, 'end' => $i === $offset + $entityLength - 1];
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
                        'pre' => "\n```\n",
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
        $firstLine = strtok($markdown, "\n");
        if ($firstLine === false) {
            return '';
        }

        $title = trim($firstLine);
        $title = preg_replace('/^#{1,6}\s+/', '', $title);
        $title = preg_replace('/\*\*(.+?)\*\*/', '$1', $title);
        $title = preg_replace('/\*(.+?)\*/', '$1', $title);
        $title = preg_replace('/`(.+?)`/', '$1', $title);
        $title = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $title);
        $title = trim($title);

        if (mb_strlen($title) > 100) {
            $title = mb_substr($title, 0, 100);
            $lastSpace = mb_strrpos($title, ' ');
            if ($lastSpace !== false && $lastSpace > 50) {
                $title = mb_substr($title, 0, $lastSpace);
            }
        }

        return $title;
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

        if (!is_dir($assetsDir)) {
            mkdir($assetsDir, 0o755, true);
        }

        $targetPath = $assetsDir . '/' . basename($relativePath);

        $counter = 1;
        while (is_file($targetPath)) {
            $info = pathinfo($relativePath);
            $targetPath = $assetsDir . '/' . ($info['filename'] ?? 'file') . '-' . $counter . '.' . ($info['extension'] ?? '');
            $counter++;
        }

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

    private function slugify(string $title): string
    {
        $slug = mb_strtolower($title);
        $slug = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');

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
