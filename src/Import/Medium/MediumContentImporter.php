<?php

declare(strict_types=1);

namespace YiiPress\Import\Medium;

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
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function pathinfo;
use function preg_match;
use function preg_replace;
use function preg_split;
use function sort;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strpos;
use function substr;
use function trim;
use function ucfirst;
use function ucwords;
use function yaml_parse;

use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

final class MediumContentImporter implements ContentImporterInterface
{
    public function options(): array
    {
        return [
            new ImporterOption(
                name: 'directory',
                description: 'Path to a Medium Markdown export directory',
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
                warnings: ['directory option is required and must be a valid Medium Markdown export path'],
            );
        }

        $contentDirectory = $this->contentDirectory($sourceDirectory);
        $contentFiles = $this->contentFiles($contentDirectory);

        $collectionDir = $targetDirectory . '/' . $collection;
        FileHelper::ensureDirectory($collectionDir, 0o755);

        $importedFiles = [];
        $skippedFiles = [];
        $warnings = [];
        $usedPaths = [];

        foreach ($contentFiles as $contentFile) {
            $entry = $this->readContentFile($contentFile);
            if ($entry === null) {
                $skippedFiles[] = $contentFile;
                $warnings[] = 'Skipped unreadable Medium Markdown file: ' . basename($contentFile);
                continue;
            }

            $path = $this->uniquePath($collectionDir, $this->filename($entry), $usedPaths);
            file_put_contents($path, $this->buildMarkdownFile($entry));
            $importedFiles[] = $path;
        }

        $this->ensureCollectionConfig($collectionDir, $collection);

        return new ImportResult(
            totalMessages: count($contentFiles),
            importedCount: count($importedFiles),
            importedFiles: $importedFiles,
            skippedFiles: $skippedFiles,
            warnings: $warnings,
        );
    }

    public function name(): string
    {
        return 'medium';
    }

    private function contentDirectory(string $sourceDirectory): string
    {
        $postsDirectory = $sourceDirectory . '/posts';

        return is_dir($postsDirectory) ? $postsDirectory : $sourceDirectory;
    }

    /**
     * @return list<string>
     */
    private function contentFiles(string $contentDirectory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($contentDirectory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if ($item->isFile() && mb_strtolower($item->getExtension()) === 'md') {
                $files[] = $item->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return array{
     *     title: string,
     *     slug: string,
     *     date: string,
     *     origin: string,
     *     draft: bool,
     *     tags: list<string>,
     *     categories: list<string>,
     *     body: string
     * }|null
     */
    private function readContentFile(string $contentFile): ?array
    {
        $content = file_get_contents($contentFile);
        if ($content === false) {
            return null;
        }

        [$fields, $body] = $this->splitFrontMatter($content);
        $filename = pathinfo($contentFile, PATHINFO_FILENAME);
        $date = $this->datePart($this->stringField($fields['date'] ?? $fields['published_at'] ?? null));
        if ($date === '' && preg_match('/^(\d{4}-\d{2}-\d{2})-(.+)$/', $filename, $matches) === 1) {
            $date = $matches[1];
            $filename = $matches[2];
        }

        $slug = $this->filesystemSlug($this->stringField($fields['slug'] ?? null, $filename));
        $title = $this->stringField($fields['title'] ?? null);
        if ($title === '') {
            $title = $this->titleFromBody($body) ?: ucwords(str_replace(['-', '_'], ' ', $slug));
        }

        return [
            'title' => $title,
            'slug' => $slug,
            'date' => $date,
            'origin' => $this->stringField($fields['canonical_url'] ?? $fields['url'] ?? null),
            'draft' => ($fields['draft'] ?? false) === true || ($fields['published'] ?? true) === false,
            'tags' => $this->listField($fields['tags'] ?? []),
            'categories' => $this->listField($fields['categories'] ?? []),
            'body' => trim($body) . "\n",
        ];
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
        if (!is_array($data)) {
            return [[], substr($content, $endPosition + 5)];
        }

        $fields = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $fields[$key] = $value;
            }
        }

        return [$fields, substr($content, $endPosition + 5)];
    }

    /**
     * @param array{
     *     title: string,
     *     slug: string,
     *     date: string,
     *     origin: string,
     *     draft: bool,
     *     tags: list<string>,
     *     categories: list<string>,
     *     body: string
     * } $entry
     */
    private function filename(array $entry): string
    {
        return ($entry['date'] !== '' ? $entry['date'] . '-' : '') . $entry['slug'] . '.md';
    }

    /**
     * @param array<string, true> $usedPaths
     */
    private function uniquePath(string $directory, string $filename, array &$usedPaths): string
    {
        $path = $directory . '/' . $filename;
        if (!isset($usedPaths[$path]) && !file_exists($path)) {
            $usedPaths[$path] = true;
            return $path;
        }

        $base = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $suffix = 2;
        do {
            $path = $directory . '/' . $base . '-' . $suffix . ($extension !== '' ? '.' . $extension : '');
            $suffix++;
        } while (isset($usedPaths[$path]) || file_exists($path));

        $usedPaths[$path] = true;

        return $path;
    }

    /**
     * @param array{
     *     title: string,
     *     slug: string,
     *     date: string,
     *     origin: string,
     *     draft: bool,
     *     tags: list<string>,
     *     categories: list<string>,
     *     body: string
     * } $entry
     */
    private function buildMarkdownFile(array $entry): string
    {
        $frontMatter = "---\n";
        $frontMatter .= 'title: ' . $this->yamlEscape($entry['title']) . "\n";

        if ($entry['date'] !== '') {
            $frontMatter .= 'date: ' . $entry['date'] . "\n";
        }

        if ($entry['origin'] !== '') {
            $frontMatter .= 'origin: ' . $this->yamlEscape($entry['origin']) . "\n";
        }

        if ($entry['draft']) {
            $frontMatter .= "draft: true\n";
        }

        if ($entry['tags'] !== []) {
            $frontMatter .= "tags:\n";
            foreach ($entry['tags'] as $tag) {
                $frontMatter .= '  - ' . $this->yamlEscape($tag) . "\n";
            }
        }

        if ($entry['categories'] !== []) {
            $frontMatter .= "categories:\n";
            foreach ($entry['categories'] as $category) {
                $frontMatter .= '  - ' . $this->yamlEscape($category) . "\n";
            }
        }

        return $frontMatter . "---\n\n" . $entry['body'];
    }

    private function titleFromBody(string $body): string
    {
        return preg_match('/^#\s+(.+)$/m', $body, $matches) === 1 ? trim($matches[1]) : '';
    }

    private function datePart(string $date): string
    {
        return preg_match('/^(\d{4}-\d{2}-\d{2})/', $date, $matches) === 1 ? $matches[1] : '';
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

        $items = str_contains($value, ',')
            ? explode(',', $value)
            : (preg_split('/\s+/', $value) ?: []);

        return array_values(array_filter(array_map(static fn (string $item): string => trim($item), $items)));
    }

    private function filesystemSlug(string $slug): string
    {
        $slug = str_replace(['/', '\\'], '-', trim($slug));
        $slug = (string) preg_replace('/[<>:"|?*\x00-\x1F]+/', '-', $slug);
        $slug = trim($slug, ". \t\n\r\0\x0B-");

        return $slug === '' ? 'post' : $slug;
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
        $value = str_replace(["\r", "\n"], ' ', $value);
        if (preg_match('/[:#\[\]{}|>&*!,\'"%@`]/', $value) === 1) {
            return '"' . addcslashes($value, '"\\') . '"';
        }

        return $value;
    }
}
