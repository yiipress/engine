<?php

declare(strict_types=1);

namespace YiiPress\Extension\Archive;

use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\PortablePath;

use function dirname;
use function fclose;
use function file_exists;
use function file_get_contents;
use function fopen;
use function fread;
use function fwrite;
use function is_dir;
use function is_file;
use function is_link;
use function min;
use function mkdir;
use function preg_match;
use function rtrim;
use function sprintf;
use function str_repeat;
use function strlen;
use function strpos;
use function substr;

/**
 * Unpacks a `.tar.gz` release archive as a stream, without `PharData` and without running anything from it.
 *
 * Accepts regular files, directories and pax headers only, strips the enclosing directory, checks every path
 * with {@see PortablePath} and enforces {@see InstallLimits} while reading.
 */
final class TarGzReader
{
    private const int CHUNK = 65_536;
    private const int MAX_PAX_BYTES = 65_536;

    /** @var resource */
    private $stream;
    private string $target;
    private InstallLimits $limits;
    private ArchivePathIndex $paths;
    private ?string $enclosing = null;
    private ?string $commit = null;
    private int $entries = 0;
    private int $extracted = 0;

    /**
     * @param string $file Path of the downloaded `.tar.gz`.
     * @param string $targetDir Directory to create and unpack into; must not exist.
     */
    public function extract(string $file, string $targetDir, InstallLimits $limits): ExtractedArchive
    {
        $this->open($file);
        $this->target = $targetDir;
        $this->limits = $limits;
        $this->paths = new ArchivePathIndex();
        $this->enclosing = null;
        $this->commit = null;
        $this->entries = 0;
        $this->extracted = 0;
        $this->createTarget();

        try {
            $this->readEntries();
        } finally {
            fclose($this->stream);
        }

        if (!is_file($targetDir . '/extension.yaml') || is_link($targetDir . '/extension.yaml') || !is_dir($targetDir . '/package')) {
            throw new InstallException(
                'archive_layout',
                'The release archive must hold extension.yaml and a package/ directory inside one top-level directory.',
            );
        }
        if ($this->commit === null || preg_match('/^[0-9a-f]{40}$/D', $this->commit) !== 1) {
            throw new InstallException(
                'archive_commit_missing',
                'The release archive does not name its commit in the pax global header; '
                . 'only GitHub tag archives are supported.',
            );
        }

        return new ExtractedArchive($this->commit, $targetDir);
    }

    private function open(string $file): void
    {
        $magic = @file_get_contents($file, length: 2);
        if ($magic !== "\x1f\x8b") {
            throw TarHeader::invalid('it is not a gzip file');
        }
        $stream = @fopen('compress.zlib://' . $file, 'rb');
        if ($stream === false) {
            throw TarHeader::invalid('it cannot be opened');
        }
        $this->stream = $stream;
    }

    private function createTarget(): void
    {
        if (file_exists($this->target) || is_link($this->target)) {
            throw new InstallException('filesystem_failed', sprintf('"%s" already exists.', $this->target));
        }
        if (!@mkdir($this->target, 0o755, true)) {
            throw new InstallException('filesystem_failed', sprintf('Could not create "%s".', $this->target));
        }
    }

    private function readEntries(): void
    {
        /** @var array<string, string> $pax */
        $pax = [];
        while (true) {
            $block = $this->read(TarHeader::BLOCK);
            if ($block === str_repeat("\0", TarHeader::BLOCK)) {
                if ($this->read(TarHeader::BLOCK) !== $block) {
                    throw TarHeader::invalid('the end-of-archive marker is incomplete');
                }
                return;
            }

            $header = TarHeader::parse($block);
            if ($header->type === 'g' || $header->type === 'x') {
                $records = PaxRecords::parse($this->readPax($header->size));
                if ($header->type === 'g') {
                    $this->commit = $records['comment'] ?? $this->commit;
                } else {
                    $pax = $records;
                }
                continue;
            }

            $path = $pax['path'] ?? $header->path;
            $size = isset($pax['size']) ? $this->paxSize($pax['size']) : $header->size;
            $pax = [];
            $this->entry($header->type, $path, $size);
        }
    }

    private function entry(string $type, string $path, int $size): void
    {
        if ($type === '1' || $type === '2') {
            throw new InstallException('archive_link', sprintf(
                'The release archive entry "%s" is a link; extension archives must not contain links.',
                $path,
            ));
        }
        $directory = $type === '5';
        if (!$directory && $type !== '0' && $type !== "\0") {
            throw new InstallException('archive_entry_type', sprintf(
                'The release archive entry "%s" has unsupported type "%s"; only files and directories are allowed.',
                $path,
                $type === "\0" ? '\0' : $type,
            ));
        }
        if ($directory && $size !== 0) {
            // Data of a directory would be decompressed without counting toward the extracted size.
            throw TarHeader::invalid(sprintf('directory entry "%s" carries %d bytes of data', $path, $size));
        }
        if (++$this->entries > $this->limits->entries) {
            throw new InstallException('archive_too_many_entries', sprintf(
                'The release archive has more than %d entries.',
                $this->limits->entries,
            ));
        }

        $relative = $this->stripEnclosing($directory ? rtrim($path, '/') : $path, $directory);
        if ($relative === null) {
            return;
        }
        $problem = PortablePath::problem($relative);
        if ($problem !== null) {
            throw new InstallException('archive_path_invalid', sprintf('Release archive path "%s" %s.', $relative, $problem));
        }
        $this->paths->add($relative, $directory);

        if ($directory) {
            $this->makeDirectory($this->target . '/' . $relative);
            return;
        }
        $this->writeFile($relative, $size);
    }

    /**
     * Returns the path without the enclosing directory, or `null` for the enclosing directory itself.
     */
    private function stripEnclosing(string $path, bool $directory): ?string
    {
        $slash = strpos($path, '/');
        $first = $slash === false ? $path : substr($path, 0, $slash);
        if (($slash === false && !$directory) || ($this->enclosing !== null && $first !== $this->enclosing)) {
            throw new InstallException('archive_layout', sprintf(
                'The release archive entry "%s" is outside the single top-level directory "%s".',
                $path,
                $this->enclosing ?? $first,
            ));
        }
        $this->enclosing = $first;

        return $slash === false ? null : substr($path, $slash + 1);
    }

    private function writeFile(string $relative, int $size): void
    {
        if ($size > $this->limits->fileBytes) {
            throw new InstallException('archive_file_too_large', sprintf(
                'The release archive file "%s" is larger than %d bytes.',
                $relative,
                $this->limits->fileBytes,
            ));
        }
        $this->extracted += $size;
        if ($this->extracted > $this->limits->extractedBytes) {
            throw new InstallException('archive_too_large', sprintf(
                'The release archive unpacks to more than %d bytes.',
                $this->limits->extractedBytes,
            ));
        }

        $path = $this->target . '/' . $relative;
        $this->makeDirectory(dirname($path));
        $handle = @fopen($path, 'xb');
        if ($handle === false) {
            throw new InstallException('filesystem_failed', sprintf('Could not create "%s".', $path));
        }
        try {
            for ($left = $size; $left > 0; $left -= self::CHUNK) {
                $chunk = $this->read(min(self::CHUNK, $left));
                if (@fwrite($handle, $chunk) !== strlen($chunk)) {
                    throw new InstallException('filesystem_failed', sprintf('Could not write "%s".', $path));
                }
            }
        } finally {
            fclose($handle);
        }
        $this->read(TarHeader::padding($size));
    }

    private function makeDirectory(string $path): void
    {
        if (!is_dir($path) && !@mkdir($path, 0o755, true) && !is_dir($path)) {
            throw new InstallException('filesystem_failed', sprintf('Could not create "%s".', $path));
        }
    }

    private function readPax(int $size): string
    {
        if ($size > self::MAX_PAX_BYTES) {
            throw TarHeader::invalid('a pax header is too large');
        }
        $data = $this->read($size);
        $this->read(TarHeader::padding($size));

        return $data;
    }

    private function paxSize(string $value): int
    {
        if (preg_match('/^\d{1,18}$/D', $value) !== 1) {
            throw TarHeader::invalid('a pax size is not a number');
        }

        return (int) $value;
    }

    /**
     * Reads exactly `$length` bytes or fails as a truncated archive.
     */
    private function read(int $length): string
    {
        $data = '';
        while (($missing = $length - strlen($data)) > 0) {
            $chunk = @fread($this->stream, $missing);
            if ($chunk === false || $chunk === '') {
                throw TarHeader::invalid('it ends unexpectedly');
            }
            $data .= $chunk;
        }

        return $data;
    }
}
