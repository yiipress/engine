<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Restore;

use PHPUnit\Framework\TestCase;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Manifest\ThemeFallback;
use YiiPress\Extension\Restore\InstalledPackage;

use function file_put_contents;
use function hash;
use function mkdir;
use function str_repeat;
use function symlink;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class InstalledPackageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-installed-package-test-' . uniqid();
        mkdir($this->root . '/themes/wonder/assets', 0o755, true);
        file_put_contents($this->root . '/themes/wonder/entry.php', '<?php');
        file_put_contents($this->root . '/themes/wonder/assets/style.css', 'body{}');
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    public function testMatchingFilesAreIntact(): void
    {
        $package = InstalledPackage::inspect($this->root, self::locked());

        self::assertTrue($package->isIntact());
        self::assertTrue($package->exists);
        self::assertSame([], $package->missing);
    }

    public function testMissingDirectoryMissesEveryFile(): void
    {
        DirectoryRemover::remove($this->root . '/themes');

        $package = InstalledPackage::inspect($this->root, self::locked());

        self::assertFalse($package->exists);
        self::assertSame(['assets/style.css', 'entry.php'], $package->missing);
        self::assertSame([], $package->modified);
        self::assertSame([], $package->unexpected);
        self::assertNull($package->collision);
    }

    public function testMissingFilesAreListed(): void
    {
        DirectoryRemover::remove($this->root . '/themes/wonder/assets');

        $package = InstalledPackage::inspect($this->root, self::locked());

        self::assertFalse($package->isIntact());
        self::assertTrue($package->exists);
        self::assertSame(['assets/style.css'], $package->missing);
    }

    public function testChangedFileIsModified(): void
    {
        file_put_contents($this->root . '/themes/wonder/entry.php', '<?php // mine');

        $package = InstalledPackage::inspect($this->root, self::locked());

        self::assertSame(['entry.php'], $package->modified);
        self::assertSame([], $package->missing);
    }

    public function testFilesAndLinksOutsideTheLockAreUnexpected(): void
    {
        file_put_contents($this->root . '/themes/wonder/notes.txt', 'mine');
        symlink($this->root . '/themes/wonder/entry.php', $this->root . '/themes/wonder/assets/link.php');

        $package = InstalledPackage::inspect($this->root, self::locked());

        self::assertSame(['assets/link.php', 'notes.txt'], $package->unexpected);
    }

    public function testDirectoryWhereLockedFileShouldBeIsUnexpected(): void
    {
        unlink($this->root . '/themes/wonder/entry.php');
        mkdir($this->root . '/themes/wonder/entry.php');
        file_put_contents($this->root . '/themes/wonder/entry.php/x', 'x');

        $package = InstalledPackage::inspect($this->root, self::locked());

        self::assertSame(['entry.php/x'], $package->unexpected);
        self::assertSame(['entry.php'], $package->missing);
    }

    public function testFileAtDestinationIsCollision(): void
    {
        DirectoryRemover::remove($this->root . '/themes/wonder');
        file_put_contents($this->root . '/themes/wonder', 'a file');

        $package = InstalledPackage::inspect($this->root, self::locked());

        self::assertSame('themes/wonder', $package->collision);
    }

    public function testLinkAtDestinationIsCollision(): void
    {
        mkdir($this->root . '/elsewhere');
        DirectoryRemover::remove($this->root . '/themes/wonder');
        symlink($this->root . '/elsewhere', $this->root . '/themes/wonder');

        $package = InstalledPackage::inspect($this->root, self::locked());

        self::assertSame('themes/wonder', $package->collision);
    }

    public function testDestinationInAnotherCaseIsCollision(): void
    {
        DirectoryRemover::remove($this->root . '/themes/wonder');
        mkdir($this->root . '/themes/Wonder');

        $package = InstalledPackage::inspect($this->root, self::locked());

        self::assertSame('themes/Wonder', $package->collision);
    }

    public static function locked(string $destination = 'themes/wonder', string $name = 'wonder'): LockedExtension
    {
        return new LockedExtension(
            type: ExtensionType::Theme,
            source: 'https://github.com/yiipress/theme-wonder',
            version: '1.0.0',
            tag: 'v1.0.0',
            commit: str_repeat('a', 40),
            archiveKind: LockedExtension::ARCHIVE_GITHUB_TARBALL,
            archiveUrl: 'https://codeload.github.com/yiipress/theme-wonder/tar.gz/refs/tags/v1.0.0',
            archiveSha256: str_repeat('b', 64),
            archiveSize: 100,
            destination: $destination,
            name: $name,
            fallback: ThemeFallback::Minimal,
            manifestSha256: str_repeat('c', 64),
            files: ['assets/style.css' => hash('sha256', 'body{}'), 'entry.php' => hash('sha256', '<?php')],
        );
    }
}
