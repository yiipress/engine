<?php

declare(strict_types=1);

namespace YiiPress\Tests\Support;

use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Lock\LockedExtension;

use function array_keys;
use function dirname;
use function sha1;
use function str_repeat;

/**
 * Manifest and lock entry of the `acme/toc` processor used by the processor tests.
 */
final class ProcessorFixture
{
    /**
     * @param string $entryPoints YAML list of `processor.entry_points`, indented by four spaces.
     */
    public static function manifest(
        string $version = '1.0.0',
        string $entryPoints = "    - path: toc.php\n      stages: [before_markdown]\n",
    ): string {
        return "manifest: 1\nid: acme/toc\ntype: processor\nname: TOC\ndescription: Adds a table of contents.\n"
            . "version: $version\nyiipress: \">=0.1.0\"\nlicense: MIT\nauthors: [{name: Jane Doe}]\n"
            . "processor:\n  entry_points:\n" . $entryPoints;
    }

    /**
     * A release of `acme/processor-toc` as {@see FakeHttpClient::gitHub()} serves it.
     *
     * @param array<string, string> $files Path in `package/` → contents.
     * @return array{manifest: string, archive: string}
     */
    public static function release(string $version, array $files, ?string $entryPoints = null): array
    {
        $manifest = $entryPoints === null ? self::manifest($version) : self::manifest($version, $entryPoints);
        $prefix = 'processor-toc-' . $version;
        $archive = TarGzBuilder::gitArchive($prefix, sha1($version))
            ->file($prefix . '/extension.yaml', $manifest)
            ->directory($prefix . '/package/');
        $directories = [];
        foreach (array_keys($files) as $path) {
            $directory = dirname($path);
            if ($directory !== '.' && !isset($directories[$directory])) {
                $directories[$directory] = true;
                $archive->directory($prefix . '/package/' . $directory . '/');
            }
        }
        foreach ($files as $path => $contents) {
            $archive->file($prefix . '/package/' . $path, $contents);
        }

        return ['manifest' => $manifest, 'archive' => $archive->gzip()];
    }

    /**
     * @param array<string, array{manifest: string, archive: string}> $releases Tag → release.
     */
    public static function http(array $releases): FakeHttpClient
    {
        return new FakeHttpClient()->gitHub('acme/processor-toc', $releases);
    }

    /**
     * @param array<string, string> $files Path relative to the package → SHA-256.
     */
    public static function locked(
        string $destination = 'content/extensions/processors/acme/toc',
        array $files = [],
        string $version = '1.0.0',
    ): LockedExtension {
        return new LockedExtension(
            ExtensionType::Processor,
            'https://github.com/acme/processor-toc',
            $version,
            'v' . $version,
            str_repeat('a', 40),
            LockedExtension::ARCHIVE_GITHUB_TARBALL,
            'https://codeload.github.com/acme/processor-toc/tar.gz/refs/tags/v' . $version,
            str_repeat('b', 64),
            10,
            $destination,
            null,
            null,
            str_repeat('c', 64),
            $files,
        );
    }
}
