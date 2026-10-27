<?php

declare(strict_types=1);

namespace YiiPress\Extension\Manifest;

use YiiPress\Extension\ExtensionType;

use function array_filter;
use function array_merge;
use function array_values;
use function file_get_contents;
use function filesize;
use function is_dir;
use function is_file;
use function is_readable;
use function rtrim;
use function sprintf;

use const DIRECTORY_SEPARATOR;

/**
 * Reads `extension.yaml` from an unpacked package and checks it against the package files.
 *
 * Package code is never included or executed: manifest paths are only checked with `is_file()` and `realpath()`.
 */
final class ManifestReader
{
    public const string FILE_NAME = 'extension.yaml';
    public const string PACKAGE_DIR = 'package';

    public function __construct(
        private readonly ManifestParser $parser = new ManifestParser(),
    ) {}

    /**
     * @param string $packageRoot Directory that holds `extension.yaml` and `package/`.
     * @param ExtensionType|null $expected Type the caller installs; another type is an error.
     *
     * @throws InvalidManifestException When the manifest or the package has errors; it lists all of them.
     */
    public function read(string $packageRoot, ?ExtensionType $expected = null): ExtensionManifest
    {
        $packageRoot = rtrim($packageRoot, '/\\');
        $yaml = $this->readFile($packageRoot);

        $errors = [];
        $packageDir = $packageRoot . DIRECTORY_SEPARATOR . self::PACKAGE_DIR;
        if (!is_dir($packageDir)) {
            $errors[] = new ManifestError(
                'package_dir_missing',
                '',
                sprintf('The package has no %s/ directory next to extension.yaml.', self::PACKAGE_DIR),
            );
        }

        try {
            $manifest = $this->parser->parse($yaml);
        } catch (InvalidManifestException $exception) {
            throw new InvalidManifestException(array_merge($errors, $exception->errors()));
        }

        $errors = array_merge($errors, $this->typeErrors($manifest, $expected), $this->pathErrors($manifest, $packageRoot));
        if ($errors !== []) {
            throw new InvalidManifestException($errors);
        }

        return $manifest;
    }

    private function readFile(string $packageRoot): string
    {
        $path = $packageRoot . DIRECTORY_SEPARATOR . self::FILE_NAME;
        $error = match (true) {
            !is_file($path) && !is_dir($path) => new ManifestError(
                'file_missing',
                '',
                sprintf('No %s found in "%s".', self::FILE_NAME, $packageRoot),
            ),
            !is_file($path) || !is_readable($path) => new ManifestError(
                'file_unreadable',
                '',
                sprintf('%s in "%s" is not a readable file.', self::FILE_NAME, $packageRoot),
            ),
            filesize($path) > YamlDocumentLoader::MAX_BYTES => new ManifestError(
                'file_too_large',
                '',
                sprintf('%s in "%s" is larger than 64 KiB.', self::FILE_NAME, $packageRoot),
            ),
            default => null,
        };
        $yaml = $error === null ? file_get_contents($path) : false;
        if ($yaml === false) {
            throw new InvalidManifestException([$error ?? new ManifestError(
                'file_unreadable',
                '',
                sprintf('%s in "%s" could not be read.', self::FILE_NAME, $packageRoot),
            )]);
        }

        return $yaml;
    }

    /**
     * @return list<ManifestError>
     */
    private function typeErrors(ExtensionManifest $manifest, ?ExtensionType $expected): array
    {
        if ($expected === null || $manifest->type === $expected) {
            return [];
        }

        return [new ManifestError('type_mismatch', 'type', sprintf(
            'type: "%s" does not match the expected type "%s".',
            $manifest->type->value,
            $expected->value,
        ))];
    }

    /**
     * @return list<ManifestError>
     */
    private function pathErrors(ExtensionManifest $manifest, string $packageRoot): array
    {
        $packageDir = $packageRoot . DIRECTORY_SEPARATOR . self::PACKAGE_DIR;
        $packageBase = new PackagePathBase($packageDir, self::PACKAGE_DIR . '/');
        $catalogBase = new PackagePathBase($packageRoot, 'the package root');

        $errors = [];
        foreach ($manifest->processor()->entryPoints ?? [] as $index => $entryPoint) {
            $errors[] = $packageBase->check($entryPoint->path, sprintf('processor.entry_points[%d].path', $index));
        }
        foreach ($manifest->processor()->examples ?? [] as $index => $example) {
            $errors[] = $catalogBase->check($example->input, sprintf('processor.examples[%d].input', $index));
            $errors[] = $catalogBase->check($example->output, sprintf('processor.examples[%d].output', $index));
        }
        foreach ($manifest->theme()->screenshots ?? [] as $index => $screenshot) {
            $errors[] = $catalogBase->check($screenshot, sprintf('theme.screenshots[%d]', $index));
        }

        return array_values(array_filter($errors));
    }
}
