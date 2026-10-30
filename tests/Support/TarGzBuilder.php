<?php

declare(strict_types=1);

namespace YiiPress\Tests\Support;

use function file_put_contents;
use function gzencode;
use function ord;
use function sprintf;
use function str_pad;
use function str_repeat;
use function strlen;
use function substr_replace;

/**
 * Builds ustar/pax archives byte by byte, including broken ones, the way `git archive` lays them out.
 */
final class TarGzBuilder
{
    private string $tar = '';

    public static function gitArchive(string $prefix, string $commit = 'acf0346e709f2934037914577a98a6ee62a28000'): self
    {
        $builder = new self();
        $builder->globalHeader(['comment' => $commit]);
        $builder->directory($prefix . '/');

        return $builder;
    }

    /**
     * @param array<string, string> $records
     */
    public function globalHeader(array $records): self
    {
        return $this->entry('pax_global_header', 'g', self::paxRecords($records));
    }

    /**
     * @param array<string, string> $records
     */
    public function paxHeader(array $records): self
    {
        return $this->entry('PaxHeader', 'x', self::paxRecords($records));
    }

    public function directory(string $path): self
    {
        return $this->entry($path, '5', '');
    }

    public function file(string $path, string $contents): self
    {
        return $this->entry($path, '0', $contents);
    }

    public function symlink(string $path, string $target): self
    {
        return $this->entry($path, '2', '', linkName: $target);
    }

    /**
     * Adds a raw entry. `$declaredSize` overrides the size field without changing the data written.
     */
    public function entry(
        string $name,
        string $type,
        string $contents,
        string $linkName = '',
        ?int $declaredSize = null,
        bool $corruptChecksum = false,
        string $prefix = '',
    ): self {
        $header = str_pad($name, 100, "\0")
            . sprintf("%07o\0", 0o644)
            . sprintf("%07o\0", 0)
            . sprintf("%07o\0", 0)
            . sprintf("%011o\0", $declaredSize ?? strlen($contents))
            . sprintf("%011o\0", 0)
            . '        '
            . $type
            . str_pad($linkName, 100, "\0")
            . "ustar\0" . '00'
            . str_pad('', 32, "\0")
            . str_pad('', 32, "\0")
            . str_pad('', 16, "\0")
            . str_pad($prefix, 155, "\0");
        $header = str_pad($header, 512, "\0");

        $sum = 0;
        for ($i = 0; $i < 512; $i++) {
            $sum += ord($header[$i]);
        }
        if ($corruptChecksum) {
            $sum++;
        }
        $header = substr_replace($header, sprintf("%06o\0 ", $sum), 148, 8);

        $this->tar .= $header . self::padded($contents);

        return $this;
    }

    /**
     * Archive bytes, uncompressed.
     */
    public function tar(bool $endBlocks = true): string
    {
        return $this->tar . ($endBlocks ? str_repeat("\0", 1024) : '');
    }

    public function gzip(bool $endBlocks = true): string
    {
        return (string) gzencode($this->tar($endBlocks));
    }

    public function writeTo(string $file, bool $endBlocks = true): string
    {
        file_put_contents($file, $this->gzip($endBlocks));

        return $file;
    }

    private static function padded(string $contents): string
    {
        $remainder = strlen($contents) % 512;

        return $remainder === 0 ? $contents : $contents . str_repeat("\0", 512 - $remainder);
    }

    /**
     * @param array<string, string> $records
     */
    private static function paxRecords(array $records): string
    {
        $data = '';
        foreach ($records as $key => $value) {
            $body = ' ' . $key . '=' . $value . "\n";
            $length = strlen($body) + 1;
            while (strlen((string) $length) + strlen($body) !== $length) {
                $length = strlen((string) $length) + strlen($body);
            }
            $data .= $length . $body;
        }

        return $data;
    }
}
