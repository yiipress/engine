<?php

declare(strict_types=1);

namespace YiiPress\Import\Telegram;

use YiiPress\Build\FileWriter;
use YiiPress\Import\ContentImporterInterface;
use YiiPress\Import\ImporterOption;
use YiiPress\Import\ImportResult;
use Yiisoft\Files\FileHelper;

use function count;
use function is_array;
use function is_int;
use function is_string;

/**
 * @phpstan-import-type ChannelData from Channel
 * @phpstan-import-type MessageData from Message
 * @phpstan-type ExportMessage array{id: int, type: string, action?: string, poll?: mixed, text?: mixed, photo?: mixed, file?: mixed, ...}
 */
final class TelegramContentImporter implements ContentImporterInterface
{
    public function options(): array
    {
        return [
            new ImporterOption(
                name: 'directory',
                description: 'Path to the Telegram export directory containing result.json',
                required: true,
                path: true,
            ),
            new ImporterOption(
                name: 'ignore_message_ids',
                description: 'Comma-separated list of message IDs to skip during import',
                required: false,
                default: '',
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

        $encoding = mb_detect_encoding($json, ['UTF-16LE', 'UTF-16BE', 'UTF-8', 'Windows-1251'], true);
        if ($encoding === false) {
            return new ImportResult(0, 0, [], [], ['Unable to detect result.json encoding.']);
        }
        $json = mb_convert_encoding($json, 'UTF-8', $encoding);
        if ($json === false) {
            return new ImportResult(0, 0, [], [], ['Unable to convert result.json to UTF-8.']);
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

        if (!isset($data['type'], $data['messages']) || $data['type'] !== 'public_channel' || !is_array($data['messages'])) {
            return new ImportResult(
                totalMessages: 0,
                importedCount: 0,
                importedFiles: [],
                skippedFiles: [],
                warnings: ['result.json structure does not match expected format. Only "public_channel" type is supported.'],
            );
        }

        $collectionDir = $targetDirectory . '/' . $collection;

        FileHelper::ensureDirectory($collectionDir, 0o755);

        $assetsDir = $collectionDir . '/assets';

        $importedFiles = [];
        $skippedFiles = [];
        $warnings = [];

        $messages = array_values($data['messages']);
        $totalMessages = count($messages);

        $ignoredIds = $this->parseIgnoredIds($options['ignore_message_ids'] ?? '');

        $channel = null;
        foreach ($messages as $index => $dataMessage) {
            $dataMessage = self::normalizeExportMessage($dataMessage);
            if ($dataMessage === null) {
                $warnings[] = "Skipped malformed Telegram record at index $index.";
                continue;
            }
            if (
                $dataMessage['type'] === 'service' &&
                array_key_exists('action', $dataMessage) &&
                $dataMessage['action'] === 'create_channel'
            ) {
                /** @var ChannelData $dataMessage */
                $channel = new Channel($dataMessage);
                continue;
            }

            if ($dataMessage['type'] !== 'message') {
                $skippedFiles[] = $dataMessage['id'];
                continue;
            }

            /** @var MessageData $dataMessage */

            if (in_array($dataMessage['id'], $ignoredIds, true)) {
                $skippedFiles[] = $dataMessage['id'];
                continue;
            }

            if (!empty($dataMessage['poll'])) {
                $skippedFiles[] = $dataMessage['id'];
                // TODO: import polls
                continue;
            }

            if ($dataMessage['text'] === '' && empty($dataMessage['photo']) && empty($dataMessage['file'])) {
                $skippedFiles[] = $dataMessage['id'];
                continue;
            }

            $message = new Message($dataMessage, $channel);

            $filename = $message->date->format('Y-m-d') . '-' . $message->slug . '.md';
            $filePath = $collectionDir . '/' . $filename;

            $mediaPath = '';
            if ($message->photo !== null) {
                $mediaPath = $this->copyMedia($sourceDirectory, $message->photo, $assetsDir);
            }

            if ($mediaPath === '' && $message->file !== null) {
                $mediaPath = $this->copyMedia($sourceDirectory, $message->file, $assetsDir);
            }

            $content = $this->buildMarkdownFile($message, $mediaPath, $collection);
            FileWriter::write($filePath, $content);
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

    /** @return ExportMessage|null */
    private static function normalizeExportMessage(mixed $value): ?array
    {
        if (!is_array($value) || !is_int($value['id'] ?? null) || !is_string($value['type'] ?? null)) {
            return null;
        }

        if ($value['type'] === 'service' && ($value['action'] ?? null) === 'create_channel') {
            if (!is_string($value['title'] ?? null) || !self::isTimestamp($value['date_unixtime'] ?? null)) {
                return null;
            }
            /** @var ExportMessage $value */
            return $value;
        }

        if ($value['type'] !== 'message') {
            /** @var ExportMessage $value */
            return $value;
        }

        $text = $value['text'] ?? '';
        if (!is_string($text) && !self::isTextParts($text)) {
            return null;
        }
        $entities = $value['text_entities'] ?? [];
        if (!self::isTextEntities($entities)) {
            return null;
        }
        foreach (['photo', 'file', 'forwarded_from'] as $key) {
            if (isset($value[$key]) && !is_string($value[$key])) {
                return null;
            }
        }
        foreach (['date_unixtime', 'edited_unixtime'] as $key) {
            if (isset($value[$key]) && !self::isTimestamp($value[$key])) {
                return null;
            }
        }

        $value['text'] = $text;
        $value['text_entities'] = $entities;
        /** @var ExportMessage $value */
        return $value;
    }

    private static function isTimestamp(mixed $value): bool
    {
        return is_int($value) || is_string($value) && is_numeric($value);
    }

    /** @phpstan-assert-if-true list<string|array{type: string, text: string, language?: string, href?: string}> $value */
    private static function isTextParts(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $part) {
            if (is_string($part)) {
                continue;
            }
            if (!is_array($part) || !is_string($part['type'] ?? null) || !is_string($part['text'] ?? null)) {
                return false;
            }
            if (isset($part['language']) && !is_string($part['language']) || isset($part['href']) && !is_string($part['href'])) {
                return false;
            }
        }
        return true;
    }

    /** @phpstan-assert-if-true list<array{type: string, text?: string, offset?: int, length?: int, href?: string, language?: string}> $value */
    private static function isTextEntities(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $entity) {
            if (!is_array($entity) || !is_string($entity['type'] ?? null)) {
                return false;
            }
            foreach (['text', 'href', 'language'] as $key) {
                if (isset($entity[$key]) && !is_string($entity[$key])) {
                    return false;
                }
            }
            foreach (['offset', 'length'] as $key) {
                if (isset($entity[$key]) && !is_int($entity[$key])) {
                    return false;
                }
            }
        }
        return true;
    }

    public function name(): string
    {
        return 'telegram';
    }

    private function buildMarkdownFile(
        Message $message,
        string $mediaPath,
        string $collection,
    ): string {
        $frontMatter = "---\n";
        $frontMatter .= 'title: ' . $this->yamlEscape($message->title) . "\n";
        $frontMatter .= 'date: ' . $message->date->format('Y-m-d H:i:s') . "\n";
        $frontMatter .= 'edited: ' . $message->edited->format('Y-m-d H:i:s') . "\n";

        if ($message->forwardedFrom !== null) {
            $frontMatter .= 'origin: ' . $this->yamlEscape($message->forwardedFrom) . "\n";
        }

        if ($message->tags !== []) {
            $frontMatter .= "tags:\n";
            foreach ($message->tags as $tag) {
                $frontMatter .= '  - ' . $tag . "\n";
            }
        }

        if ($message->photo !== null && $mediaPath !== '') {
            $frontMatter .= 'image: /' . $collection . '/assets/' . basename($mediaPath) . "\n";
        }

        $frontMatter .= "---\n\n";

        $content = $frontMatter;

        if ($mediaPath !== '') {
            $content .= '![](/' . $collection . '/assets/' . basename($mediaPath) . ")\n\n";
        }

        $content .= $message->markdown;

        return $content;
    }

    private function copyMedia(string $sourceDirectory, string $relativePath, string $assetsDir): string
    {
        if (str_starts_with($relativePath, '/') || str_starts_with($relativePath, '\\')) {
            return '';
        }

        $sourcePath = $sourceDirectory . '/' . $relativePath;
        if (!is_file($sourcePath)) {
            return '';
        }

        $realSourceDir = realpath($sourceDirectory);
        $realSourcePath = realpath($sourcePath);
        if ($realSourceDir === false || $realSourcePath === false) {
            return '';
        }

        if (!str_starts_with($realSourcePath, $realSourceDir . DIRECTORY_SEPARATOR)) {
            return '';
        }

        $info = pathinfo($relativePath);
        $extension = $info['extension'] ?? '';
        $targetFilename = $info['filename'];
        if ($extension !== '') {
            $targetFilename .= '.' . $extension;
        }
        $targetPath = $assetsDir . '/' . $targetFilename;

        FileHelper::copyFile($realSourcePath, $targetPath, ['dirMode' => 0o755]);

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

        FileWriter::write($configPath, $config);
    }

    /** @return list<int> */
    private function parseIgnoredIds(string $ignoreMessageIds): array
    {
        if ($ignoreMessageIds === '') {
            return [];
        }

        $ids = [];
        foreach (explode(',', $ignoreMessageIds) as $id) {
            $trimmed = trim($id);
            if ($trimmed !== '' && is_numeric($trimmed)) {
                $ids[] = (int) $trimmed;
            }
        }

        return $ids;
    }

    private function yamlEscape(string $value): string
    {
        if (preg_match('/[:#\[\]{}|>&*!,\'"%@`]/', $value) === 1) {
            return '"' . addcslashes($value, '"\\') . '"';
        }

        return $value;
    }
}
