<?php

declare(strict_types=1);

namespace YiiPress\Import\Ghost;

use JsonException;
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
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function mb_strtolower;
use function pathinfo;
use function preg_match;
use function preg_replace;
use function str_replace;
use function str_starts_with;
use function trim;
use function ucfirst;

use const JSON_THROW_ON_ERROR;
use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

final class GhostContentImporter implements ContentImporterInterface
{
    public function options(): array
    {
        return [
            new ImporterOption(
                name: 'file',
                description: 'Path to a Ghost JSON export file',
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
                warnings: ['file option is required and must be a valid Ghost JSON export file'],
            );
        }

        $json = file_get_contents($sourceFile);
        if ($json === false) {
            return new ImportResult(
                totalMessages: 0,
                importedCount: 0,
                importedFiles: [],
                skippedFiles: [],
                warnings: ["Failed to read $sourceFile"],
            );
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new ImportResult(
                totalMessages: 0,
                importedCount: 0,
                importedFiles: [],
                skippedFiles: [],
                warnings: ["Invalid Ghost JSON in $sourceFile"],
            );
        }

        if (!is_array($data)) {
            return new ImportResult(
                totalMessages: 0,
                importedCount: 0,
                importedFiles: [],
                skippedFiles: [],
                warnings: ["Invalid Ghost JSON structure in $sourceFile"],
            );
        }

        $export = $this->exportData($data);
        if ($export === null) {
            return new ImportResult(
                totalMessages: 0,
                importedCount: 0,
                importedFiles: [],
                skippedFiles: [],
                warnings: ["Ghost export data not found in $sourceFile"],
            );
        }

        $posts = $this->list($export['posts'] ?? []);
        $tagsByPost = $this->tagsByPost($export);
        $authorsByPost = $this->authorsByPost($export);

        FileHelper::ensureDirectory($targetDirectory, 0o755);

        $collectionDir = $targetDirectory . '/' . $collection;
        $importedFiles = [];
        $skippedFiles = [];
        $usedPaths = [];
        $hasCollectionEntries = false;

        foreach ($posts as $post) {
            $entry = $this->readPost($post, $tagsByPost, $authorsByPost);
            if ($entry === null) {
                $skippedFiles[] = $this->stringValue($post['id'] ?? null, $this->stringValue($post['slug'] ?? null));
                continue;
            }

            $directory = $targetDirectory;
            if ($entry['type'] === 'post') {
                FileHelper::ensureDirectory($collectionDir, 0o755);
                $directory = $collectionDir;
                $hasCollectionEntries = true;
            }

            $path = $this->uniquePath($directory, $this->filename($entry), $usedPaths);
            file_put_contents($path, $this->buildMarkdownFile($entry));
            $importedFiles[] = $path;
        }

        if ($hasCollectionEntries) {
            $this->ensureCollectionConfig($collectionDir, $collection);
        }

        return new ImportResult(
            totalMessages: count($posts),
            importedCount: count($importedFiles),
            importedFiles: $importedFiles,
            skippedFiles: $skippedFiles,
            warnings: [],
        );
    }

    public function name(): string
    {
        return 'ghost';
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>|null
     */
    private function exportData(array $data): ?array
    {
        if (isset($data['db']) && is_array($data['db'])) {
            $first = $data['db'][0] ?? null;
            if (is_array($first) && isset($first['data']) && is_array($first['data'])) {
                return $first['data'];
            }
        }

        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }

        return isset($data['posts']) && is_array($data['posts']) ? $data : null;
    }

    /**
     * @param mixed $value
     * @return list<array<array-key, mixed>>
     */
    private function list(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param array<array-key, mixed> $export
     * @return array<string, list<string>>
     */
    private function tagsByPost(array $export): array
    {
        $tags = [];
        foreach ($this->list($export['tags'] ?? []) as $tag) {
            $id = $this->stringValue($tag['id'] ?? null);
            $slug = $this->filesystemSlug($this->stringValue($tag['slug'] ?? null, $this->stringValue($tag['name'] ?? null)));
            if ($id !== '') {
                $tags[$id] = $slug;
            }
        }

        $byPost = [];
        foreach ($this->list($export['posts_tags'] ?? []) as $relation) {
            $postId = $this->stringValue($relation['post_id'] ?? null);
            $tagId = $this->stringValue($relation['tag_id'] ?? null);
            if ($postId !== '' && isset($tags[$tagId])) {
                $byPost[$postId][$tags[$tagId]] = true;
            }
        }

        return $this->flattenMap($byPost);
    }

    /**
     * @param array<array-key, mixed> $export
     * @return array<string, list<string>>
     */
    private function authorsByPost(array $export): array
    {
        $authors = [];
        foreach ($this->list($export['users'] ?? []) as $author) {
            $id = $this->stringValue($author['id'] ?? null);
            $slug = $this->filesystemSlug($this->stringValue($author['slug'] ?? null, $this->stringValue($author['name'] ?? null)));
            if ($id !== '') {
                $authors[$id] = $slug;
            }
        }

        $byPost = [];
        foreach ($this->list($export['posts_authors'] ?? []) as $relation) {
            $postId = $this->stringValue($relation['post_id'] ?? null);
            $authorId = $this->stringValue($relation['author_id'] ?? null);
            if ($postId !== '' && isset($authors[$authorId])) {
                $byPost[$postId][$authors[$authorId]] = true;
            }
        }

        return $this->flattenMap($byPost);
    }

    /**
     * @param array<string, array<string, true>> $map
     * @return array<string, list<string>>
     */
    private function flattenMap(array $map): array
    {
        $result = [];
        foreach ($map as $postId => $values) {
            $result[$postId] = array_keys($values);
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $post
     * @param array<string, list<string>> $tagsByPost
     * @param array<string, list<string>> $authorsByPost
     * @return array{
     *     id: string,
     *     type: 'post'|'page',
     *     title: string,
     *     slug: string,
     *     date: string,
     *     permalink: string,
     *     draft: bool,
     *     summary: string,
     *     image: string,
     *     body: string,
     *     tags: list<string>,
     *     authors: list<string>
     * }|null
     */
    private function readPost(array $post, array $tagsByPost, array $authorsByPost): ?array
    {
        $type = $this->stringValue($post['type'] ?? null, 'post');
        if (!in_array($type, ['post', 'page'], true)) {
            return null;
        }
        /** @var 'post'|'page' $type */

        $status = $this->stringValue($post['status'] ?? null);
        if (in_array($status, ['deleted'], true)) {
            return null;
        }

        $id = $this->stringValue($post['id'] ?? null);
        $title = $this->stringValue($post['title'] ?? null);
        $slug = $this->filesystemSlug($this->stringValue($post['slug'] ?? null));
        if ($slug === 'post') {
            $slug = $this->slugFromTitle($title);
        }
        if ($title === '') {
            $title = ucfirst(str_replace('-', ' ', $slug));
        }

        $body = $this->stringValue($post['html'] ?? null);
        if ($body === '') {
            $body = $this->stringValue($post['markdown'] ?? null, $this->stringValue($post['plaintext'] ?? null));
        }

        return [
            'id' => $id,
            'type' => $type,
            'title' => $title,
            'slug' => $slug,
            'date' => $this->stringValue($post['published_at'] ?? null, $this->stringValue($post['created_at'] ?? null)),
            'permalink' => $type === 'page' ? '/' . $slug . '/' : '',
            'draft' => $status !== '' && $status !== 'published',
            'summary' => $this->stringValue($post['custom_excerpt'] ?? null, $this->stringValue($post['excerpt'] ?? null)),
            'image' => $this->imagePath($this->stringValue($post['feature_image'] ?? null)),
            'body' => trim($body) . "\n",
            'tags' => $id !== '' ? ($tagsByPost[$id] ?? []) : [],
            'authors' => $id !== '' ? ($authorsByPost[$id] ?? []) : [],
        ];
    }

    private function imagePath(string $image): string
    {
        if (str_starts_with($image, '__GHOST_URL__')) {
            return str_replace('__GHOST_URL__', '', $image);
        }

        return $image;
    }

    /**
     * @param array{
     *     id: string,
     *     type: 'post'|'page',
     *     title: string,
     *     slug: string,
     *     date: string,
     *     permalink: string,
     *     draft: bool,
     *     summary: string,
     *     image: string,
     *     body: string,
     *     tags: list<string>,
     *     authors: list<string>
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
     *     id: string,
     *     type: 'post'|'page',
     *     title: string,
     *     slug: string,
     *     date: string,
     *     permalink: string,
     *     draft: bool,
     *     summary: string,
     *     image: string,
     *     body: string,
     *     tags: list<string>,
     *     authors: list<string>
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

        if ($entry['image'] !== '') {
            $frontMatter .= 'image: ' . $this->yamlEscape($entry['image']) . "\n";
        }

        if ($entry['tags'] !== []) {
            $frontMatter .= "tags:\n";
            foreach ($entry['tags'] as $tag) {
                $frontMatter .= '  - ' . $this->yamlEscape($tag) . "\n";
            }
        }

        if ($entry['authors'] !== []) {
            $frontMatter .= "authors:\n";
            foreach ($entry['authors'] as $author) {
                $frontMatter .= '  - ' . $this->yamlEscape($author) . "\n";
            }
        }

        return $frontMatter . "---\n\n" . $entry['body'];
    }

    private function stringValue(mixed $value, string $default = ''): string
    {
        if (!is_string($value)) {
            return $default;
        }

        return trim($value);
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
