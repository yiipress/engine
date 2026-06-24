<?php

declare(strict_types=1);

namespace YiiPress\Import\Hugo;

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

final class HugoContentImporter implements ContentImporterInterface
{
    public function options(): array
    {
        return [
            new ImporterOption(
                name: 'directory',
                description: 'Path to the Hugo site directory',
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
                warnings: ['directory option is required and must be a valid Hugo site path'],
            );
        }

        $contentDirectory = $this->contentDirectory($sourceDirectory);
        if ($contentDirectory === '') {
            return new ImportResult(
                totalMessages: 0,
                importedCount: 0,
                importedFiles: [],
                skippedFiles: [],
                warnings: ["content directory not found in $sourceDirectory"],
            );
        }

        $collectionDir = $targetDirectory . '/' . $collection;
        FileHelper::ensureDirectory($collectionDir, 0o755);

        $contentFiles = $this->contentFiles($contentDirectory);
        $importedFiles = [];
        $skippedFiles = [];
        $warnings = [];

        foreach ($contentFiles as $contentFile) {
            $post = $this->readContentFile($contentFile);
            if ($post === null) {
                $skippedFiles[] = $contentFile;
                $warnings[] = 'Skipped unreadable Hugo content file: ' . basename($contentFile);
                continue;
            }

            [$date, $slug, $fields, $body] = $post;
            $filename = ($date !== '' ? $date . '-' : '') . $slug . '.md';
            $targetPath = $collectionDir . '/' . $filename;
            file_put_contents($targetPath, $this->buildMarkdownFile($date, $slug, $fields, $body));
            $importedFiles[] = $targetPath;
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
        return 'hugo';
    }

    private function contentDirectory(string $sourceDirectory): string
    {
        foreach (['content/posts', 'content/post', 'content'] as $relativePath) {
            $path = $sourceDirectory . '/' . $relativePath;
            if (is_dir($path)) {
                return $path;
            }
        }

        return '';
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
            if ($item->isFile() && strtolower($item->getExtension()) === 'md') {
                $files[] = $item->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>, 3: string}|null
     */
    private function readContentFile(string $contentFile): ?array
    {
        $content = file_get_contents($contentFile);
        if ($content === false) {
            return null;
        }

        [$fields, $body] = $this->splitFrontMatter($content);
        $filename = pathinfo($contentFile, PATHINFO_FILENAME);
        $date = $this->datePart($this->stringField($fields['date'] ?? null));
        if ($date === '' && preg_match('/^(\d{4}-\d{2}-\d{2})-(.+)$/', $filename, $matches) === 1) {
            $date = $matches[1];
            $filename = $matches[2];
        }

        $slug = $this->stringField($fields['slug'] ?? null);
        if ($slug === '') {
            $slug = $filename;
        }
        $slug = $this->filesystemSlug($slug);

        return [$date, $slug, $fields, trim($body) . "\n"];
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function splitFrontMatter(string $content): array
    {
        $content = str_replace("\r\n", "\n", $content);
        if (str_starts_with($content, "---\n")) {
            return $this->splitDelimitedFrontMatter($content, '---', $this->parseYaml(...));
        }
        if (str_starts_with($content, "+++\n")) {
            return $this->splitDelimitedFrontMatter($content, '+++', $this->parseToml(...));
        }

        return [[], $content];
    }

    /**
     * @param callable(string): array<string, mixed> $parser
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function splitDelimitedFrontMatter(string $content, string $delimiter, callable $parser): array
    {
        $endPosition = strpos($content, "\n" . $delimiter . "\n", 4);
        if ($endPosition === false) {
            return [[], $content];
        }

        $fields = $parser(substr($content, 4, $endPosition - 4));
        $body = substr($content, $endPosition + 5);

        return [$fields, $body];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseYaml(string $frontMatter): array
    {
        $data = yaml_parse($frontMatter);
        if (!is_array($data)) {
            return [];
        }

        $fields = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $fields[$key] = $value;
            }
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseToml(string $frontMatter): array
    {
        $fields = [];
        foreach (explode("\n", $frontMatter) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            $equalsPosition = strpos($line, '=');
            if ($equalsPosition === false) {
                continue;
            }

            $key = trim(substr($line, 0, $equalsPosition));
            if ($key === '') {
                continue;
            }

            $fields[$key] = $this->parseTomlValue(trim(substr($line, $equalsPosition + 1)));
        }

        return $fields;
    }

    private function parseTomlValue(string $value): mixed
    {
        if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
            $items = trim(substr($value, 1, -1));
            if ($items === '') {
                return [];
            }

            return array_map(
                fn (string $item): string => $this->unquoteTomlString(trim($item)),
                explode(',', $items),
            );
        }

        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }

        return $this->unquoteTomlString($value);
    }

    private function unquoteTomlString(string $value): string
    {
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            return substr($value, 1, -1);
        }

        return $value;
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

        $dateValue = $this->stringField($fields['date'] ?? null, $date);
        if ($dateValue !== '') {
            $frontMatter .= 'date: ' . $dateValue . "\n";
        }

        $permalink = $this->stringField($fields['url'] ?? null);
        if ($permalink === '') {
            $permalink = $this->stringField($fields['permalink'] ?? null);
        }
        if ($permalink !== '') {
            $frontMatter .= 'permalink: ' . $this->yamlEscape($permalink) . "\n";
        }

        if (($fields['draft'] ?? false) === true) {
            $frontMatter .= "draft: true\n";
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

    private function datePart(string $date): string
    {
        return preg_match('/^(\d{4}-\d{2}-\d{2})/', $date, $matches) === 1 ? $matches[1] : '';
    }

    private function titleFromBody(string $body): string
    {
        return preg_match('/^#\s+(.+)$/m', $body, $matches) === 1 ? trim($matches[1]) : '';
    }

    private function filesystemSlug(string $slug): string
    {
        $slug = str_replace(['/', '\\'], '-', trim($slug));
        $slug = (string) preg_replace('/[<>:"|?*\x00-\x1F]+/', '-', $slug);
        $slug = trim($slug, ". \t\n\r\0\x0B-");

        return $slug === '' ? 'post' : $slug;
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
