<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Processor;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\ExtensionId;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Manifest\ExtensionManifest;
use YiiPress\Extension\Manifest\ManifestParser;
use YiiPress\Extension\Processor\ProcessorDestinationPolicy;
use YiiPress\Tests\Support\ProcessorFixture as Fixture;

use function file_put_contents;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

final class ProcessorDestinationPolicyTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-processor-policy-test-' . uniqid();
        mkdir($this->root . '/content', 0o755, true);
        file_put_contents($this->root . '/content/config.yaml', "title: Blog\n");
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function contentDirectories(): iterable
    {
        yield 'default' => ['content', 'content/extensions/processors/acme/toc'];
        yield 'nested' => ['site/content/', 'site/content/extensions/processors/acme/toc'];
        yield 'dot segment' => ['./content', 'content/extensions/processors/acme/toc'];
        yield 'project root' => ['.', 'extensions/processors/acme/toc'];
    }

    #[DataProvider('contentDirectories')]
    public function testDestinationIsInContentDirectory(string $contentDir, string $expected): void
    {
        $destination = new ProcessorDestinationPolicy($contentDir)
            ->destination(self::manifest(), LockFile::empty(), $this->root);

        self::assertSame($expected, $destination);
    }

    public function testAcceptsAbsoluteContentDirectoryInsideProject(): void
    {
        $destination = new ProcessorDestinationPolicy($this->root . '/content')
            ->destination(self::manifest(), LockFile::empty(), $this->root);

        self::assertSame('content/extensions/processors/acme/toc', $destination);
    }

    public function testHasNoLocalNameAndNoActivation(): void
    {
        $policy = new ProcessorDestinationPolicy();

        self::assertNull($policy->localName(self::manifest()));
        self::assertNull($policy->activation(self::manifest(), $this->root));
        self::assertFileDoesNotExist($this->root . '/content/extensions');
    }

    public function testRefusesInstalledId(): void
    {
        $lock = LockFile::empty()->withExtension(self::id('acme/toc'), Fixture::locked());

        $this->assertErrorCode('already_installed', fn() => new ProcessorDestinationPolicy()
            ->destination(self::manifest(), $lock, $this->root));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function existingEntries(): iterable
    {
        yield 'package directory' => ['content/extensions/processors/acme/toc'];
        yield 'package directory in another case' => ['content/extensions/processors/Acme/TOC'];
    }

    #[DataProvider('existingEntries')]
    public function testRefusesExistingDirectory(string $path): void
    {
        mkdir($this->root . '/' . $path, 0o755, true);

        $this->assertErrorCode('destination_exists', fn() => new ProcessorDestinationPolicy()
            ->destination(self::manifest(), LockFile::empty(), $this->root));
    }

    public function testRefusesFileInPlaceOfPackage(): void
    {
        mkdir($this->root . '/content/extensions/processors/acme', 0o755, true);
        file_put_contents($this->root . '/content/extensions/processors/acme/toc', 'a file');

        $this->assertErrorCode('destination_exists', fn() => new ProcessorDestinationPolicy()
            ->destination(self::manifest(), LockFile::empty(), $this->root));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedContentDirectories(): iterable
    {
        yield 'outside the project' => ['/elsewhere'];
        yield 'parent segment' => ['../content'];
    }

    #[DataProvider('unsupportedContentDirectories')]
    public function testRefusesContentDirectory(string $contentDir): void
    {
        $this->assertErrorCode('config_unsupported', fn() => new ProcessorDestinationPolicy($contentDir)
            ->destination(self::manifest(), LockFile::empty(), $this->root));
    }

    private function assertErrorCode(string $code, callable $action): void
    {
        try {
            $action();
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame($code, $exception->errorCode, $exception->getMessage());
        }
    }

    private static function manifest(): ExtensionManifest
    {
        return new ManifestParser()->parse(Fixture::manifest());
    }

    private static function id(string $id): ExtensionId
    {
        return ExtensionId::tryFromString($id) ?? throw new LogicException('Invalid ID.');
    }
}
