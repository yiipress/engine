<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Manifest;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Manifest\InvalidManifestException;
use YiiPress\Extension\Manifest\ManifestError;
use YiiPress\Extension\Manifest\ManifestReader;

use function PHPUnit\Framework\assertSame;

final class ManifestReaderTest extends TestCase
{
    private const string PROCESSOR = <<<'YAML'
        manifest: 1
        id: example/emoji
        type: processor
        name: Emoji
        description: "Replaces :shortcodes: with emoji."
        version: 0.2.0
        yiipress: ^0.1.13
        license: MIT
        authors: [{name: Example Team}]
        processor:
          entry_points:
            - path: src/emoji.php
              stages: [before_markdown]
          examples:
            - input: examples/in.md
              output: examples/out.html
        YAML;

    private const string THEME = <<<'YAML'
        manifest: 1
        id: yiipress/wonder
        type: theme
        name: Wonder
        description: A clean blog theme.
        version: 1.0.0
        yiipress: ^0.1.13
        license: BSD-3-Clause
        authors: [{name: Jane Doe}]
        theme:
          screenshots: [screenshots/home.png]
        YAML;

    private string $root;
    private string $outside;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/yiipress-manifest-reader-test-' . uniqid();
        $this->root = $base . '/package-root';
        $this->outside = $base . '/outside';
        mkdir($this->root . '/package/src', 0o755, true);
        mkdir($this->root . '/examples', 0o755, true);
        mkdir($this->outside, 0o755, true);
        file_put_contents($this->root . '/extension.yaml', self::PROCESSOR);
        file_put_contents($this->root . '/package/src/emoji.php', '<?php return static fn(string $c): string => $c;');
        file_put_contents($this->root . '/examples/in.md', ':smile:');
        file_put_contents($this->root . '/examples/out.html', '<p>😄</p>');
        file_put_contents($this->outside . '/evil.php', '<?php');
    }

    protected function tearDown(): void
    {
        self::removeDir(dirname($this->root));
    }

    public function testReadsValidProcessorPackage(): void
    {
        $manifest = new ManifestReader()->read($this->root, ExtensionType::Processor);

        assertSame('example/emoji', (string) $manifest->id);
    }

    public function testReadsValidThemePackage(): void
    {
        file_put_contents($this->root . '/extension.yaml', self::THEME);
        mkdir($this->root . '/screenshots');
        file_put_contents($this->root . '/screenshots/home.png', 'png');

        $manifest = new ManifestReader()->read($this->root);

        assertSame(ExtensionType::Theme, $manifest->type);
    }

    public function testReportsMissingFile(): void
    {
        unlink($this->root . '/extension.yaml');

        $error = $this->onlyError();

        assertSame('file_missing', $error->code);
        assertSame(sprintf('No extension.yaml found in "%s".', $this->root), $error->message);
    }

    public function testReportsUnreadableFile(): void
    {
        unlink($this->root . '/extension.yaml');
        mkdir($this->root . '/extension.yaml');

        assertSame('file_unreadable', $this->onlyError()->code);
    }

    public function testReportsTooLargeFileWithoutParsingIt(): void
    {
        file_put_contents($this->root . '/extension.yaml', str_repeat('#', 65537));

        assertSame('file_too_large', $this->onlyError()->code);
    }

    public function testReportsMissingPackageDirectoryWithParseErrors(): void
    {
        self::removeDir($this->root . '/package');
        file_put_contents($this->root . '/extension.yaml', str_replace('version: 0.2.0', 'version: v1', self::PROCESSOR));

        $codes = array_map(static fn(ManifestError $e): string => $e->code, $this->errors());

        assertSame(['package_dir_missing', 'version_invalid'], $codes);
    }

    public function testReportsTypeMismatch(): void
    {
        $error = $this->onlyError(ExtensionType::Theme);

        assertSame('type_mismatch', $error->code);
        assertSame('type: "processor" does not match the expected type "theme".', $error->message);
    }

    public function testReportsMissingEntryPoint(): void
    {
        unlink($this->root . '/package/src/emoji.php');

        $error = $this->onlyError();

        assertSame('path_not_found', $error->code);
        assertSame('processor.entry_points[0].path', $error->field);
        assertSame('processor.entry_points[0].path: file "src/emoji.php" does not exist in package/.', $error->message);
    }

    public function testReportsEntryPointThatIsDirectory(): void
    {
        unlink($this->root . '/package/src/emoji.php');
        mkdir($this->root . '/package/src/emoji.php');

        assertSame('path_not_found', $this->onlyError()->code);
    }

    public function testReportsEntryPointOutsidePackageDirectory(): void
    {
        file_put_contents($this->root . '/emoji.php', '<?php');
        file_put_contents($this->root . '/extension.yaml', str_replace('path: src/emoji.php', 'path: ../emoji.php', self::PROCESSOR));

        // `..` is rejected by the path rules before the filesystem is touched.
        assertSame('path_invalid', $this->onlyError()->code);
    }

    public function testReportsSymlinkLeavingPackageDirectory(): void
    {
        unlink($this->root . '/package/src/emoji.php');
        symlink($this->outside . '/evil.php', $this->root . '/package/src/emoji.php');

        $error = $this->onlyError();

        assertSame('path_outside', $error->code);
        assertSame('processor.entry_points[0].path: file "src/emoji.php" resolves to a location outside package/.', $error->message);
    }

    public function testReportsMissingCatalogFile(): void
    {
        unlink($this->root . '/examples/out.html');

        $error = $this->onlyError();

        assertSame('path_not_found', $error->code);
        assertSame('processor.examples[0].output: file "examples/out.html" does not exist in the package root.', $error->message);
    }

    public function testReportsMissingScreenshot(): void
    {
        file_put_contents($this->root . '/extension.yaml', self::THEME);

        $error = $this->onlyError();

        assertSame('path_not_found', $error->code);
        assertSame('theme.screenshots[0]', $error->field);
    }

    /**
     * @return list<ManifestError>
     */
    private function errors(?ExtensionType $expected = null): array
    {
        try {
            new ManifestReader()->read($this->root, $expected);
        } catch (InvalidManifestException $exception) {
            return $exception->errors();
        }

        self::fail('Expected an invalid package.');
    }

    private function onlyError(?ExtensionType $expected = null): ManifestError
    {
        $errors = $this->errors($expected);
        if (count($errors) !== 1) {
            self::fail('Expected one error, got: ' . implode('; ', array_map(static fn(ManifestError $e) => $e->message, $errors)));
        }

        return $errors[0];
    }

    private static function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if ($item->isDir() && !$item->isLink()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($path);
    }
}
