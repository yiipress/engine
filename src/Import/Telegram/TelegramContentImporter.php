<?php

declare(strict_types=1);

namespace App\Import\Telegram;

use App\Import\ContentImporterInterface;
use App\Import\ImporterOption;
use App\Import\ImportResult;
use RuntimeException;

use function count;
use function is_array;

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

        $json = mb_convert_encoding($json, 'UTF-8', mb_detect_encoding($json, ['UTF-16LE', 'UTF-16BE', 'UTF-8', 'Windows-1251'], true));

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

        if (!is_dir($collectionDir) && !mkdir($collectionDir, 0o755, true) && !is_dir($collectionDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $collectionDir));
        }

        $assetsDir = $collectionDir . '/assets';

        $importedFiles = [];
        $skippedFiles = [];
        $warnings = [];

        $totalMessages = count($data['messages']);

        $channel = null;
        foreach ($data['messages'] as $dataMessage) {
            if (
                $dataMessage['type'] === 'service' &&
                array_key_exists('action', $dataMessage) &&
                $dataMessage['action'] === 'create_channel'
            ) {
                $channel = new Channel($dataMessage);
                continue;
            }

            if ($dataMessage['type'] !== 'message') {
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
        if (
            $realSourceDir === false
            || $realSourcePath === false
            || !str_starts_with($realSourcePath, $realSourceDir . DIRECTORY_SEPARATOR)
        ) {
            return '';
        }

        if (!is_dir($assetsDir) && !mkdir($assetsDir, 0o755, true) && !is_dir($assetsDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $assetsDir));
        }

        $info = pathinfo($relativePath);
        $targetPath = $assetsDir . '/' . ($info['filename'] ?? 'file') . '.' . ($info['extension'] ?? '');

        copy($realSourcePath, $targetPath);

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

    private function yamlEscape(string $value): string
    {
        if (preg_match('/[:#\[\]{}|>&*!,\'"%@`]/', $value) === 1) {
            return '"' . addcslashes($value, '"\\') . '"';
        }

        return $value;
    }
}
