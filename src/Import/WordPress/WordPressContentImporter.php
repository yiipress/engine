<?php

declare(strict_types=1);

namespace YiiPress\Import\WordPress;

use Throwable;
use YiiPress\Import\ContentImporterInterface;
use YiiPress\Import\ImporterOption;
use YiiPress\Import\ImportResult;
use Yiisoft\Files\FileHelper;

use function addcslashes;
use function array_keys;
use function count;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function html_entity_decode;
use function in_array;
use function is_file;
use function is_string;
use function mb_strtolower;
use function parse_url;
use function pathinfo;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function str_replace;
use function str_starts_with;
use function strip_tags;
use function trim;
use function ucfirst;

use const ENT_QUOTES;
use const ENT_XML1;
use const PHP_URL_PATH;
use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

final class WordPressContentImporter implements ContentImporterInterface
{
    public function options(): array
    {
        return [
            new ImporterOption(
                name: 'file',
                description: 'Path to a WordPress WXR export XML file',
                required: true,
            ),
        ];
    }

    public function import(array $options, string $targetDirectory, string $collection): ImportResult
    {
        $sourceFile = $options['file'] ?? '';
        if ($sourceFile === '' || !is_file($sourceFile)) {
            return new ImportResult(
                totalMessages: 0,
                importedCount: 0,
                importedFiles: [],
                skippedFiles: [],
                warnings: ['file option is required and must be a valid WordPress WXR export file'],
            );
        }

        $xml = file_get_contents($sourceFile);
        if ($xml === false) {
            return new ImportResult(
                totalMessages: 0,
                importedCount: 0,
                importedFiles: [],
                skippedFiles: [],
                warnings: ["Failed to read $sourceFile"],
            );
        }

        $items = $this->loadItems($xml);
        if ($items === null) {
            return new ImportResult(
                totalMessages: 0,
                importedCount: 0,
                importedFiles: [],
                skippedFiles: [],
                warnings: ["Invalid WordPress WXR XML in $sourceFile"],
            );
        }

        FileHelper::ensureDirectory($targetDirectory, 0o755);

        $collectionDir = $targetDirectory . '/' . $collection;
        $importedFiles = [];
        $skippedFiles = [];
        $warnings = [];
        $usedPaths = [];
        $hasCollectionEntries = false;

        foreach ($items as $item) {
            $entry = $this->readItem($item);
            if ($entry === null) {
                $skippedFiles[] = $this->itemIdentifier($item);
                continue;
            }

            $directory = $targetDirectory;
            if ($entry['type'] === 'post') {
                FileHelper::ensureDirectory($collectionDir, 0o755);
                $directory = $collectionDir;
                $hasCollectionEntries = true;
            }

            $filename = $this->filename($entry);
            $path = $this->uniquePath($directory, $filename, $usedPaths);
            file_put_contents($path, $this->buildMarkdownFile($entry));
            $importedFiles[] = $path;
        }

        if ($hasCollectionEntries) {
            $this->ensureCollectionConfig($collectionDir, $collection);
        }

        return new ImportResult(
            totalMessages: count($items),
            importedCount: count($importedFiles),
            importedFiles: $importedFiles,
            skippedFiles: $skippedFiles,
            warnings: $warnings,
        );
    }

    public function name(): string
    {
        return 'wordpress';
    }

    /**
     * @return list<string>|null
     */
    private function loadItems(string $xml): ?array
    {
        if (trim($xml) === '') {
            return null;
        }

        if (preg_match('/<rss\b[^>]*>.*<\/rss>/is', $xml) !== 1) {
            return null;
        }

        if (preg_match('/<channel\b[^>]*>(.*?)<\/channel>/is', $xml, $matches) !== 1) {
            return null;
        }

        preg_match_all('/<item\b[^>]*>.*?<\/item>/is', $matches[1], $itemMatches);

        return $itemMatches[0];
    }

    /**
     * @return array{
     *     type: 'post'|'page',
     *     title: string,
     *     slug: string,
     *     date: string,
     *     permalink: string,
     *     draft: bool,
     *     summary: string,
     *     body: string,
     *     tags: list<string>,
     *     categories: list<string>
     * }|null
     */
    private function readItem(string $item): ?array
    {
        $type = $this->childText($item, 'wp:post_type');
        if (!in_array($type, ['post', 'page'], true)) {
            return null;
        }

        $status = $this->childText($item, 'wp:status');
        if (in_array($status, ['trash', 'auto-draft'], true)) {
            return null;
        }

        $title = $this->childText($item, 'title');
        $slug = $this->filesystemSlug($this->childText($item, 'wp:post_name'));
        if ($slug === 'post') {
            $slug = $this->slugFromTitle($title);
        }

        if ($title === '') {
            $title = ucfirst(str_replace('-', ' ', $slug));
        }

        $body = $this->childText($item, 'content:encoded');
        if ($body === '') {
            $body = $this->childText($item, 'description');
        }

        return [
            'type' => $type,
            'title' => $title,
            'slug' => $slug,
            'date' => $this->publishedDate($item),
            'permalink' => $this->permalink($item, $type, $slug),
            'draft' => $status !== '' && $status !== 'publish',
            'summary' => $this->summary($item),
            'body' => trim($body) . "\n",
            'tags' => $this->taxonomyValues($item, 'post_tag'),
            'categories' => $this->taxonomyValues($item, 'category'),
        ];
    }

    private function itemIdentifier(string $item): string
    {
        $id = $this->childText($item, 'wp:post_id');

        return $id !== '' ? $id : $this->childText($item, 'title');
    }

    private function childText(string $xml, string $name): string
    {
        $tag = preg_quote($name, '/');
        if (preg_match('/<' . $tag . '\b[^>]*>(.*?)<\/' . $tag . '>/is', $xml, $matches) !== 1) {
            return '';
        }

        return trim($this->decodeXmlText($matches[1]));
    }

    private function publishedDate(string $item): string
    {
        $date = $this->childText($item, 'wp:post_date');
        if ($date !== '' && !str_starts_with($date, '0000-00-00')) {
            return $date;
        }

        $date = $this->childText($item, 'pubDate');
        if ($date === '') {
            return '';
        }

        try {
            return (new \DateTimeImmutable($date))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return '';
        }
    }

    private function permalink(string $item, string $type, string $slug): string
    {
        $path = parse_url($this->childText($item, 'link'), PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            return str_starts_with($path, '/') ? $path : '/' . $path;
        }

        return $type === 'page' ? '/' . $slug . '/' : '';
    }

    private function summary(string $item): string
    {
        $summary = $this->childText($item, 'excerpt:encoded');
        if ($summary === '') {
            return '';
        }

        return trim(strip_tags($summary));
    }

    /**
     * @return list<string>
     */
    private function taxonomyValues(string $item, string $domain): array
    {
        $values = [];
        preg_match_all('/<category\b([^>]*)>(.*?)<\/category>/is', $item, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if ($this->attributeValue($match[1], 'domain') !== $domain) {
                continue;
            }

            $value = $this->attributeValue($match[1], 'nicename');
            if ($value === '') {
                $value = $this->slugFromTitle($this->decodeXmlText($match[2]));
            }

            if ($value !== '') {
                $values[$value] = true;
            }
        }

        return array_keys($values);
    }

    private function attributeValue(string $attributes, string $name): string
    {
        $attribute = preg_quote($name, '/');
        if (preg_match('/\b' . $attribute . '\s*=\s*(["\'])(.*?)\1/is', $attributes, $matches) !== 1) {
            return '';
        }

        return $this->decodeXmlText($matches[2]);
    }

    private function decodeXmlText(string $value): string
    {
        $value = (string) preg_replace('/<!\[CDATA\[(.*?)\]\]>/s', '$1', $value);

        return html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * @param array{
     *     type: 'post'|'page',
     *     title: string,
     *     slug: string,
     *     date: string,
     *     permalink: string,
     *     draft: bool,
     *     summary: string,
     *     body: string,
     *     tags: list<string>,
     *     categories: list<string>
     * } $entry
     */
    private function filename(array $entry): string
    {
        if ($entry['type'] === 'post') {
            $date = $this->datePart($entry['date']);

            return ($date !== '' ? $date . '-' : '') . $entry['slug'] . '.md';
        }

        return $entry['slug'] . '.md';
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
     *     type: 'post'|'page',
     *     title: string,
     *     slug: string,
     *     date: string,
     *     permalink: string,
     *     draft: bool,
     *     summary: string,
     *     body: string,
     *     tags: list<string>,
     *     categories: list<string>
     * } $entry
     */
    private function buildMarkdownFile(array $entry): string
    {
        $frontMatter = "---\n";
        $frontMatter .= 'title: ' . $this->yamlEscape($entry['title']) . "\n";

        if ($entry['date'] !== '') {
            $frontMatter .= 'date: ' . $entry['date'] . "\n";
        }

        if ($entry['permalink'] !== '') {
            $frontMatter .= 'permalink: ' . $this->yamlEscape($entry['permalink']) . "\n";
        }

        if ($entry['draft']) {
            $frontMatter .= "draft: true\n";
        }

        if ($entry['summary'] !== '') {
            $frontMatter .= 'summary: ' . $this->yamlEscape($entry['summary']) . "\n";
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

    private function datePart(string $date): string
    {
        return preg_match('/^(\d{4}-\d{2}-\d{2})/', $date, $matches) === 1 ? $matches[1] : '';
    }

    private function filesystemSlug(string $slug): string
    {
        $slug = str_replace(['/', '\\'], '-', trim($slug));
        $slug = (string) preg_replace('/[<>:"|?*\x00-\x1F]+/', '-', $slug);
        $slug = trim($slug, ". \t\n\r\0\x0B-");

        return $slug === '' ? 'post' : $slug;
    }

    private function slugFromTitle(string $title): string
    {
        $slug = (string) preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($title));
        $slug = trim($slug, '-');

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
