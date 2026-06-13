<?php

declare(strict_types=1);

namespace YiiPress\Import\Jekyll;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use YiiPress\Import\ContentImporterInterface;
use YiiPress\Import\ImporterOption;
use YiiPress\Import\ImportResult;
use Yiisoft\Files\FileHelper;

use function addcslashes;
use function array_filter;
use function array_map;
use function array_values;
use function basename;
use function count;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function is_file;
use function pathinfo;
use function preg_match;
use function preg_split;
use function sort;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function strpos;
use function substr;
use function trim;
use function ucfirst;
use function ucwords;
use function yaml_parse;

use const PATHINFO_FILENAME;

final class JekyllContentImporter implements ContentImporterInterface
{
    public function options(): array
    {
        return [
            new ImporterOption(
                name: 'directory',
                description: 'Path to the Jekyll site directory containing _posts',
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
                warnings: ['directory option is required and must be a valid Jekyll site path'],
            );
        }

        $postsDirectory = $sourceDirectory . '/_posts';
        if (!is_dir($postsDirectory)) {
            return new ImportResult(
                totalMessages: 0,
                importedCount: 0,
                importedFiles: [],
                skippedFiles: [],
                warnings: ["_posts directory not found in $sourceDirectory"],
            );
        }

        $collectionDir = $targetDirectory . '/' . $collection;
        FileHelper::ensureDirectory($collectionDir, 0o755);

        $postFiles = $this->postFiles($postsDirectory);
        $importedFiles = [];
        $skippedFiles = [];
        $warnings = [];

        foreach ($postFiles as $postFile) {
            $post = $this->readPost($postFile);
            if ($post === null) {
                $skippedFiles[] = $postFile;
                $warnings[] = 'Skipped unsupported Jekyll post filename: ' . basename($postFile);
                continue;
            }

            [$date, $slug, $fields, $body] = $post;
            $targetPath = $collectionDir . '/' . $date . '-' . $slug . '.md';
            file_put_contents($targetPath, $this->buildMarkdownFile($date, $slug, $fields, $body));
            $importedFiles[] = $targetPath;
        }

        $this->ensureCollectionConfig($collectionDir, $collection);

        return new ImportResult(
            totalMessages: count($postFiles),
            importedCount: count($importedFiles),
            importedFiles: $importedFiles,
            skippedFiles: $skippedFiles,
            warnings: $warnings,
        );
    }

    public function name(): string
    {
        return 'jekyll';
    }

    /**
     * @return list<string>
     */
    private function postFiles(string $postsDirectory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($postsDirectory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if (!$item->isFile()) {
                continue;
            }

            $extension = strtolower($item->getExtension());
            if ($extension === 'md' || $extension === 'markdown') {
                $files[] = $item->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>, 3: string}|null
     */
    private function readPost(string $postFile): ?array
    {
        $filename = pathinfo($postFile, PATHINFO_FILENAME);
        if (!preg_match('/^(\d{4}-\d{2}-\d{2})-(.+)$/', $filename, $matches)) {
            return null;
        }

        $content = file_get_contents($postFile);
        if ($content === false) {
            return null;
        }

        [$fields, $body] = $this->splitFrontMatter($content);

        return [$matches[1], $matches[2], $fields, trim($body) . "\n"];
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function splitFrontMatter(string $content): array
    {
        $content = str_replace("\r\n", "\n", $content);
        if (!str_starts_with($content, "---\n")) {
            return [[], $content];
        }

        $endPosition = strpos($content, "\n---\n", 4);
        if ($endPosition === false) {
            return [[], $content];
        }

        $data = yaml_parse(substr($content, 4, $endPosition - 4));
        $body = substr($content, $endPosition + 5);

        if (!is_array($data)) {
            return [[], $body];
        }

        $fields = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $fields[$key] = $value;
            }
        }

        return [$fields, $body];
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function buildMarkdownFile(string $date, string $slug, array $fields, string $body): string
    {
        $title = $this->stringField($fields['title'] ?? null);
        if ($title === '') {
            $title = $this->titleFromBody($body) ?: ucwords(str_replace(['-', '_'], ' ', $slug));
        }

        $frontMatter = "---\n";
        $frontMatter .= 'title: ' . $this->yamlEscape($title) . "\n";
        $frontMatter .= 'date: ' . $this->stringField($fields['date'] ?? null, $date) . "\n";

        $permalink = $this->stringField($fields['permalink'] ?? null);
        if ($permalink !== '') {
            $frontMatter .= 'permalink: ' . $this->yamlEscape($permalink) . "\n";
        }

        $tags = $this->listField($fields['tags'] ?? []);
        if ($tags !== []) {
            $frontMatter .= "tags:\n";
            foreach ($tags as $tag) {
                $frontMatter .= '  - ' . $this->yamlEscape($tag) . "\n";
            }
        }

        $categories = $this->listField($fields['categories'] ?? []);
        if ($categories !== []) {
            $frontMatter .= "categories:\n";
            foreach ($categories as $category) {
                $frontMatter .= '  - ' . $this->yamlEscape($category) . "\n";
            }
        }

        return $frontMatter . "---\n\n" . $body;
    }

    private function titleFromBody(string $body): string
    {
        return preg_match('/^#\s+(.+)$/m', $body, $matches) === 1 ? trim($matches[1]) : '';
    }

    private function stringField(mixed $value, string $default = ''): string
    {
        if ($value === null) {
            return $default;
        }

        return trim((string) $value);
    }

    /**
     * @return list<string>
     */
    private function listField(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), $value)));
        }

        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        $items = str_ends_with($value, ',') || str_contains($value, ',')
            ? explode(',', $value)
            : (preg_split('/\s+/', $value) ?: []);

        return array_values(array_filter(array_map(static fn (string $item): string => trim($item), $items)));
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
