<?php

declare(strict_types=1);

namespace YiiPress\Extension\Lock;

use JsonException;
use YiiPress\Extension\ExtensionId;
use YiiPress\Extension\Install\InstallException;

use function file_exists;
use function file_get_contents;
use function is_array;
use function is_int;
use function json_decode;
use function json_encode;
use function ksort;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const SORT_STRING;

/**
 * `yiipress.lock`: what was installed from where, so a project can be restored byte for byte.
 *
 * The same contents always serialise to the same bytes: keys sorted, no timestamps.
 */
final readonly class LockFile
{
    public const string FILE_NAME = 'yiipress.lock';
    public const int VERSION = 1;

    /**
     * @param array<string, LockedExtension> $extensions Keyed by extension ID.
     */
    private function __construct(private array $extensions = []) {}

    public static function empty(): self
    {
        return new self();
    }

    /**
     * Reads the lock file; a missing file is an empty lock.
     *
     * @throws InstallException `lock_invalid` or `lock_unsupported`.
     */
    public static function read(string $path): self
    {
        if (!file_exists($path)) {
            return new self();
        }
        $json = @file_get_contents($path);
        if ($json === false) {
            throw new InstallException('lock_invalid', sprintf('Could not read "%s".', $path));
        }

        return self::fromJson($json);
    }

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InstallException('lock_invalid', sprintf('yiipress.lock is not valid JSON: %s.', $exception->getMessage()));
        }
        if (!is_array($data)) {
            throw LockedExtensionParser::invalid('lock', 'must be an object');
        }
        $version = $data['lock'] ?? null;
        if (!is_int($version) || $version < 1) {
            throw LockedExtensionParser::invalid('lock', 'must be the integer 1');
        }
        if ($version > self::VERSION) {
            throw new InstallException('lock_unsupported', sprintf(
                'yiipress.lock has format %d; this YiiPress reads format %d. Update YiiPress.',
                $version,
                self::VERSION,
            ));
        }
        foreach ($data as $key => $_) {
            if ($key !== 'lock' && $key !== 'extensions') {
                throw LockedExtensionParser::invalid((string) $key, 'is not a known field');
            }
        }
        $entries = $data['extensions'] ?? null;
        if (!is_array($entries)) {
            throw LockedExtensionParser::invalid('extensions', 'must be an object');
        }

        $extensions = [];
        foreach ($entries as $id => $entry) {
            $id = (string) $id;
            if (ExtensionId::tryFromString($id) === null) {
                throw LockedExtensionParser::invalid("extensions.$id", 'is not an extension ID');
            }
            if (!is_array($entry)) {
                throw LockedExtensionParser::invalid("extensions.$id", 'must be an object');
            }
            $extensions[$id] = LockedExtensionParser::parse($id, $entry);
        }

        return new self($extensions);
    }

    public function get(ExtensionId $id): ?LockedExtension
    {
        return $this->extensions[(string) $id] ?? null;
    }

    public function withExtension(ExtensionId $id, LockedExtension $extension): self
    {
        $extensions = $this->extensions;
        $extensions[(string) $id] = $extension;

        return new self($extensions);
    }

    public function withoutExtension(ExtensionId $id): self
    {
        $extensions = $this->extensions;
        unset($extensions[(string) $id]);

        return new self($extensions);
    }

    /**
     * @return array<string, LockedExtension> Keyed by extension ID.
     */
    public function extensions(): array
    {
        return $this->extensions;
    }

    /**
     * @return list<string> Destination of every extension, relative to the project root.
     */
    public function destinations(): array
    {
        $destinations = [];
        foreach ($this->extensions as $extension) {
            $destinations[] = $extension->destination;
        }

        return $destinations;
    }

    public function toJson(): string
    {
        $extensions = [];
        foreach ($this->extensions as $id => $extension) {
            $extensions[$id] = $extension->toArray();
        }
        ksort($extensions, SORT_STRING);

        return json_encode(
            ['extensions' => (object) $extensions, 'lock' => self::VERSION],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ) . "\n";
    }
}
