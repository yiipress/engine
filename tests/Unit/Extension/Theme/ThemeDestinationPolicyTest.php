<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Theme;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\ExtensionId;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Manifest\ExtensionManifest;
use YiiPress\Extension\Manifest\ManifestParser;
use YiiPress\Extension\Manifest\ThemeFallback;
use YiiPress\Extension\Theme\ThemeDestinationPolicy;
use YiiPress\Tests\Unit\Extension\Release\ReleaseSelectorTest;

use function file_put_contents;
use function mkdir;
use function str_repeat;
use function sys_get_temp_dir;
use function uniqid;

final class ThemeDestinationPolicyTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-theme-policy-test-' . uniqid();
        mkdir($this->root . '/content', 0o755, true);
        file_put_contents($this->root . '/content/config.yaml', "title: Blog\ntheme: minimal\n");
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    public function testDestinationIsThemesDirectoryNamedAfterId(): void
    {
        $destination = new ThemeDestinationPolicy()->destination(self::manifest(), LockFile::empty(), $this->root);

        self::assertSame('themes/wonder', $destination);
    }

    public function testRefusesInstalledId(): void
    {
        $lock = LockFile::empty()->withExtension(self::id('yiipress/wonder'), self::locked('wonder'));

        $this->assertErrorCode('already_installed', fn() => new ThemeDestinationPolicy()
            ->destination(self::manifest(), $lock, $this->root));
    }

    public function testRefusesNameOfAnotherInstalledTheme(): void
    {
        $lock = LockFile::empty()->withExtension(self::id('acme/wonder'), self::locked('wonder'));

        $this->assertErrorCode('name_collision', fn() => new ThemeDestinationPolicy()
            ->destination(self::manifest(), $lock, $this->root));
    }

    public function testRefusesExistingEntryInAnyCase(): void
    {
        mkdir($this->root . '/themes');
        file_put_contents($this->root . '/themes/Wonder', 'a file');

        $this->assertErrorCode('destination_exists', fn() => new ThemeDestinationPolicy()
            ->destination(self::manifest(), LockFile::empty(), $this->root));
    }

    public function testActivationSetsThemeInConfig(): void
    {
        $change = new ThemeDestinationPolicy('content')->activation(self::manifest(), $this->root);

        self::assertNotNull($change);
        self::assertSame('content/config.yaml', $change->path);
        self::assertSame("title: Blog\ntheme: wonder\n", $change->contents);
    }

    public function testActivationAcceptsAbsoluteContentDirectoryInsideProject(): void
    {
        $change = new ThemeDestinationPolicy($this->root . '/content/')->activation(self::manifest(), $this->root);

        self::assertSame('content/config.yaml', $change?->path);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function contentDirectories(): iterable
    {
        yield 'plain' => ['content', 'content/config.yaml'];
        yield 'dot segment' => ['./content', 'content/config.yaml'];
        yield 'repeated slashes' => ['content//', 'content/config.yaml'];
        yield 'project root' => ['.', 'config.yaml'];
    }

    #[DataProvider('contentDirectories')]
    public function testActivationNormalisesContentDirectory(string $contentDir, string $expected): void
    {
        file_put_contents($this->root . '/config.yaml', "title: Root\n");

        $change = new ThemeDestinationPolicy($contentDir)->activation(self::manifest(), $this->root);

        self::assertSame($expected, $change?->path);
    }

    public function testNoActivationWhenTurnedOff(): void
    {
        self::assertNull(new ThemeDestinationPolicy('missing', activate: false)->activation(self::manifest(), $this->root));
    }

    public function testActivationNeedsConfig(): void
    {
        $this->assertErrorCode('config_missing', fn() => new ThemeDestinationPolicy('missing')
            ->activation(self::manifest(), $this->root));
    }

    public function testActivationRefusesContentDirectoryOutsideProject(): void
    {
        $this->assertErrorCode('config_unsupported', fn() => new ThemeDestinationPolicy('/elsewhere')
            ->activation(self::manifest(), $this->root));
    }

    public function testActivationRefusesParentSegments(): void
    {
        $this->assertErrorCode('config_unsupported', fn() => new ThemeDestinationPolicy('../content')
            ->activation(self::manifest(), $this->root));
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
        return new ManifestParser()->parse(ReleaseSelectorTest::manifest('1.0.0'));
    }

    private static function id(string $id): ExtensionId
    {
        return ExtensionId::tryFromString($id) ?? throw new LogicException('Invalid ID.');
    }

    private static function locked(string $name): LockedExtension
    {
        return new LockedExtension(
            ExtensionType::Theme,
            'https://github.com/acme/theme',
            '1.0.0',
            'v1.0.0',
            str_repeat('a', 40),
            LockedExtension::ARCHIVE_GITHUB_TARBALL,
            'https://codeload.github.com/acme/theme/tar.gz/refs/tags/v1.0.0',
            str_repeat('b', 64),
            10,
            'themes/' . $name,
            $name,
            ThemeFallback::Minimal,
            str_repeat('c', 64),
            [],
        );
    }
}
