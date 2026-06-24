<?php

declare(strict_types=1);

namespace YiiPress\Import\WordPress;

use DOMDocument;
use DOMElement;
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
use function in_array;
use function is_file;
use function is_string;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function mb_strtolower;
use function parse_url;
use function pathinfo;
use function preg_match;
use function preg_replace;
use function str_replace;
use function str_starts_with;
use function strip_tags;
use function trim;
use function ucfirst;

use const LIBXML_NOERROR;
use const LIBXML_NONET;
use const LIBXML_NOWARNING;
use const PHP_URL_PATH;
use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

final class WordPressContentImporter implements ContentImporterInterface
{
    private const string CONTENT_NS = 'http://purl.org/rss/1.0/modules/content/';
    private const string EXCERPT_NS = 'http://wordpress.org/export/1.2/excerpt/';
    private const string WP_NS = 'http://wordpress.org/export/1.2/';

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

        $document = $this->loadXml($xml);
        if ($document === null) {
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

        $items = $document->getElementsByTagName('item');
        foreach ($items as $item) {
            if (!$item instanceof DOMElement) {
                continue;
            }

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
            totalMessages: $items->length,
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

    private function loadXml(string $xml): ?DOMDocument
    {
        if ($xml === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument();
            if (!$document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                return null;
            }

            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
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
    private function readItem(DOMElement $item): ?array
    {
        $type = $this->childText($item, 'post_type', self::WP_NS);
        if (!in_array($type, ['post', 'page'], true)) {
            return null;
        }

        $status = $this->childText($item, 'status', self::WP_NS);
        if (in_array($status, ['trash', 'auto-draft'], true)) {
            return null;
        }

        $title = $this->childText($item, 'title');
        $slug = $this->filesystemSlug($this->childText($item, 'post_name', self::WP_NS));
        if ($slug === 'post') {
            $slug = $this->slugFromTitle($title);
        }

        if ($title === '') {
            $title = ucfirst(str_replace('-', ' ', $slug));
        }

        $body = $this->childText($item, 'encoded', self::CONTENT_NS);
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

    private function itemIdentifier(DOMElement $item): string
    {
        $id = $this->childText($item, 'post_id', self::WP_NS);

        return $id !== '' ? $id : $this->childText($item, 'title');
    }

    private function childText(DOMElement $element, string $localName, ?string $namespace = null): string
    {
        foreach ($element->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            if ($child->localName !== $localName) {
                continue;
            }

            if ($namespace !== null && $child->namespaceURI !== $namespace) {
                continue;
            }

            return trim($child->textContent);
        }

        return '';
    }

    private function publishedDate(DOMElement $item): string
    {
        $date = $this->childText($item, 'post_date', self::WP_NS);
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

    private function permalink(DOMElement $item, string $type, string $slug): string
    {
        $path = parse_url($this->childText($item, 'link'), PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            return str_starts_with($path, '/') ? $path : '/' . $path;
        }

        return $type === 'page' ? '/' . $slug . '/' : '';
    }

    private function summary(DOMElement $item): string
    {
        $summary = $this->childText($item, 'encoded', self::EXCERPT_NS);
        if ($summary === '') {
            return '';
        }

        return trim(strip_tags($summary));
    }

    /**
     * @return list<string>
     */
    private function taxonomyValues(DOMElement $item, string $domain): array
    {
        $values = [];
        foreach ($item->childNodes as $child) {
            if (!$child instanceof DOMElement || $child->localName !== 'category') {
                continue;
            }

            if ($child->getAttribute('domain') !== $domain) {
                continue;
            }

            $value = $child->getAttribute('nicename');
            if ($value === '') {
                $value = $this->slugFromTitle(trim($child->textContent));
            }

            if ($value !== '') {
                $values[$value] = true;
            }
        }

        return array_keys($values);
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
