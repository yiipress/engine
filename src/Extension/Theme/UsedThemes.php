<?php

declare(strict_types=1);

namespace YiiPress\Extension\Theme;

use Closure;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;
use YiiPress\Content\Parser\ContentParser;

use function array_intersect;
use function array_keys;
use function array_values;
use function bin2hex;
use function clearstatcache;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function hash_file;
use function hash_final;
use function hash_init;
use function hash_update;
use function in_array;
use function is_array;
use function is_dir;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function ksort;
use function max;
use function mkdir;
use function random_bytes;
use function rename;
use function strlen;
use function substr;
use function time;
use function unlink;

use const JSON_THROW_ON_ERROR;
use const PHP_OS_FAMILY;
use const SORT_STRING;

/**
 * Finds the themes that entries and standalone pages select in their front matter.
 *
 * Parsing every entry is the costly part, so the themes found are cached together with a fingerprint of the
 * content: the path, size, modification and change times and inode of every Markdown file and `_collection.yaml`,
 * so unchanged content is answered from `stat()` alone (on Windows, `ctime` and the inode give way to a content
 * hash). As in git's racy rule, a file whose times are too close to
 * the scan cannot be told apart by them and makes the cache miss. File times come from the clock of the content's
 * filesystem, which may run behind the local one (a network share, a volume of a virtual machine), so "too close"
 * means up to {@see CLOCK_SKEW_SECONDS} before the local time of the scan.
 */
final readonly class UsedThemes
{
    /**
     * How far the local clock may run ahead of the content's filesystem clock without missing an edit.
     */
    public const int CLOCK_SKEW_SECONDS = 120;
    private const int VERSION = 1;
    private const array SKIPPED_DIRECTORIES = ['assets', 'authors', 'data'];

    /**
     * @param Closure(): int|null $clock Current Unix time; `time()` when `null`.
     * @param bool $changeTimeIsCreationTime Whether `ctime` is the creation time, as PHP reports it on Windows.
     * A rewrite then leaves `ctime` as it was, so the fingerprint takes each file's content hash instead of
     * `ctime` and the inode.
     */
    public function __construct(
        private string $cacheFile,
        private ?Closure $clock = null,
        private bool $changeTimeIsCreationTime = PHP_OS_FAMILY === 'Windows',
    ) {}

    /**
     * @param list<string> $candidates
     * @return list<string> The candidates that an entry or a page selects, in the order given; every candidate
     * when the content cannot be parsed, since the build reports broken content itself.
     */
    public function find(array $candidates, string $contentDir): array
    {
        $scannedAt = $this->clock === null ? time() : ($this->clock)();
        [$fingerprint, $newestModification] = $this->fingerprint($contentDir);
        $themes = $this->cachedThemes($fingerprint, $newestModification);
        if ($themes === null) {
            $themes = $this->scan($contentDir);
            if ($themes === null) {
                return $candidates;
            }
            $this->store($fingerprint, $scannedAt, $themes);
        }

        return array_values(array_intersect($candidates, $themes));
    }

    /**
     * @return list<string>|null
     */
    private function scan(string $contentDir): ?array
    {
        $parser = new ContentParser();
        $themes = [];
        try {
            foreach ([$parser->parseAllEntries($contentDir), $parser->parseStandalonePages($contentDir)] as $entries) {
                foreach ($entries as $entry) {
                    if ($entry->theme !== '' && !in_array($entry->theme, $themes, true)) {
                        $themes[] = $entry->theme;
                    }
                }
            }
        } catch (Throwable) {
            return null;
        }

        return $themes;
    }

    /**
     * @return array{string, int} The fingerprint and the newest modification or change time among the files.
     */
    private function fingerprint(string $contentDir): array
    {
        clearstatcache();
        $stats = [];
        $newestModification = 0;
        if (is_dir($contentDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($contentDir, FilesystemIterator::SKIP_DOTS),
            );
            $prefixLength = strlen($contentDir) + 1;
            foreach ($files as $file) {
                /** @var SplFileInfo $file */
                $relative = substr($file->getPathname(), $prefixLength);
                if ($file->isFile() && $this->affectsEntries($relative, $file)) {
                    $modified = $file->getMTime();
                    $changed = $file->getCTime();
                    $newestModification = max($newestModification, $modified, $changed);
                    $stats[$relative] = $file->getSize() . ':' . $modified . ':' . ($this->changeTimeIsCreationTime
                        ? (string) @hash_file('xxh128', $file->getPathname())
                        : $changed . ':' . $file->getInode());
                }
            }
        }
        ksort($stats, SORT_STRING);
        $context = hash_init('xxh128');
        foreach ($stats as $relative => $stat) {
            hash_update($context, $relative . "\0" . $stat . "\n");
        }

        return [hash_final($context), $newestModification];
    }

    private function affectsEntries(string $relative, SplFileInfo $file): bool
    {
        $top = dirname($relative);
        while (dirname($top) !== '.') {
            $top = dirname($top);
        }
        if (in_array($top, self::SKIPPED_DIRECTORIES, true)) {
            return false;
        }

        return $file->getExtension() === 'md' || $file->getFilename() === '_collection.yaml';
    }

    /**
     * @return list<string>|null
     */
    private function cachedThemes(string $fingerprint, int $newestModification): ?array
    {
        $contents = @file_get_contents($this->cacheFile);
        if ($contents === false) {
            return null;
        }
        try {
            $cache = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }
        if (
            !is_array($cache)
            || ($cache['version'] ?? null) !== self::VERSION
            || ($cache['fingerprint'] ?? null) !== $fingerprint
            || !is_int($cache['scannedAt'] ?? null)
            || $newestModification >= $cache['scannedAt'] - self::CLOCK_SKEW_SECONDS
            || !is_array($cache['themes'] ?? null)
        ) {
            return null;
        }
        $themes = [];
        foreach ($cache['themes'] as $theme) {
            if (!is_string($theme)) {
                return null;
            }
            $themes[$theme] = true;
        }

        return array_keys($themes);
    }

    /**
     * Writes the cache; a cache that cannot be written only costs the next build another scan.
     *
     * @param list<string> $themes
     */
    private function store(string $fingerprint, int $scannedAt, array $themes): void
    {
        $directory = dirname($this->cacheFile);
        if (!is_dir($directory) && !@mkdir($directory, 0o755, true) && !is_dir($directory)) {
            return;
        }
        $temporary = $this->cacheFile . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $json = json_encode(
            ['version' => self::VERSION, 'fingerprint' => $fingerprint, 'scannedAt' => $scannedAt, 'themes' => $themes],
            JSON_THROW_ON_ERROR,
        );
        if (@file_put_contents($temporary, $json) === false || !@rename($temporary, $this->cacheFile)) {
            @unlink($temporary);
        }
    }
}
