<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Lifecycle;

use PHPUnit\Framework\TestCase;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\ExtensionId;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Install\InstallRequest;
use YiiPress\Extension\Install\PackageInstaller;
use YiiPress\Extension\Lifecycle\RemoveResult;
use YiiPress\Extension\Lifecycle\ThemeRemover;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Platform;
use YiiPress\Extension\Release\GitHubReleaseSource;
use YiiPress\Extension\Release\GitHubRepository;
use YiiPress\Extension\Theme\ThemeDestinationPolicy;
use YiiPress\Tests\Support\WonderFixture;

use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_dir;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const GLOB_BRACE;

final class ThemeRemoverTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-remover-test-' . uniqid();
        mkdir($this->root . '/content/blog', 0o755, true);
        file_put_contents($this->root . '/content/config.yaml', "title: Blog\nlanguages: [en]\n");
        file_put_contents($this->root . '/content/blog/_collection.yaml', "title: Blog\n");
        $this->entry('blog/hello.md', '');
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    public function testRemovesUnusedTheme(): void
    {
        $this->install(activate: false);

        $result = $this->remove();

        self::assertSame('1.0.0', $result->removed->version);
        self::assertFalse(is_dir($this->root . '/themes/wonder'));
        self::assertNull(LockFile::read($this->root . '/yiipress.lock')->get($this->id()));
        self::assertSame("title: Blog\nlanguages: [en]\n", file_get_contents($this->root . '/content/config.yaml'));
        self::assertSame([], glob($this->root . '/.yiipress/{staging,backup}/*', GLOB_BRACE));
        self::assertFileDoesNotExist($this->root . '/.yiipress/journal.json');
    }

    public function testRefusesThemeSelectedBySite(): void
    {
        $this->install(activate: true);

        $exception = $this->removeFails('theme_in_use');

        self::assertStringContainsString('content/config.yaml', $exception->getMessage());
        self::assertStringContainsString('config.yaml', (string) $exception->getSolution());
    }

    public function testRefusesThemeSelectedByEntryAndPage(): void
    {
        $this->install(activate: false);
        $this->entry('blog/styled.md', "theme: wonder\n");
        $this->entry('about.md', "theme: wonder\n");
        $this->entry('blog/other.md', "theme: minimal\n");

        $exception = $this->removeFails('theme_in_use');

        self::assertStringContainsString('content/blog/styled.md', $exception->getMessage());
        self::assertStringContainsString('content/about.md', $exception->getMessage());
        self::assertStringNotContainsString('other.md', $exception->getMessage());
        self::assertStringNotContainsString('config.yaml', $exception->getMessage());
    }

    public function testUnreadableContentBlocksRemoval(): void
    {
        $this->install(activate: false);
        file_put_contents($this->root . '/content/blog/broken.md', "---\ntitle: Broken\ndate: not a date\n---\nBody\n");

        $exception = $this->removeFails('content_unreadable');

        self::assertStringContainsString('broken.md', $exception->getMessage());
    }

    public function testUnreadableConfigBlocksRemoval(): void
    {
        $this->install(activate: false);
        file_put_contents($this->root . '/content/config.yaml', "title: Blog\nlanguages: []\n");

        $exception = $this->removeFails('content_unreadable');

        self::assertStringContainsString('"languages"', $exception->getMessage());
    }

    public function testRefusesLocalChanges(): void
    {
        $this->install(activate: false);
        file_put_contents($this->root . '/themes/wonder/mine.php', 'mine');

        $this->removeFails('local_changes');

        self::assertSame('mine', file_get_contents($this->root . '/themes/wonder/mine.php'));
    }

    public function testDropsLockEntryWhenWholeDirectoryIsGone(): void
    {
        $this->install(activate: false);
        DirectoryRemover::remove($this->root . '/themes/wonder');

        $result = $this->remove();

        self::assertFalse($result->deletedDirectory);
        self::assertNull(LockFile::read($this->root . '/yiipress.lock')->get($this->id()));
        self::assertTrue(is_dir($this->root . '/themes'));
        self::assertFileDoesNotExist($this->root . '/.yiipress/journal.json');
    }

    public function testDeletesDirectoryOfIntactTheme(): void
    {
        $this->install(activate: false);

        self::assertTrue($this->remove()->deletedDirectory);
    }

    public function testRefusesPartlyDeletedTheme(): void
    {
        $this->install(activate: false);
        unlink($this->root . '/themes/wonder/entry.php');

        $this->removeFails('local_changes');

        self::assertFileExists($this->root . '/themes/wonder/assets/style.css');
    }

    public function testRefusesFileInPlaceOfGoneDirectory(): void
    {
        $this->install(activate: false);
        DirectoryRemover::remove($this->root . '/themes/wonder');
        file_put_contents($this->root . '/themes/wonder', 'mine');

        $this->removeFails('local_changes');

        self::assertSame('mine', file_get_contents($this->root . '/themes/wonder'));
    }

    public function testRefusesGoneThemeThatIsStillSelected(): void
    {
        $this->install(activate: true);
        DirectoryRemover::remove($this->root . '/themes/wonder');

        $this->removeFails('theme_in_use');
    }

    public function testReferencesAreCheckedBeforeLocalChanges(): void
    {
        $this->install(activate: true);
        file_put_contents($this->root . '/themes/wonder/mine.php', 'mine');

        $this->removeFails('theme_in_use');
    }

    public function testRefusesThemeThatIsNotInstalled(): void
    {
        $this->removeFails('not_installed');
    }

    private function entry(string $path, string $themeLine): void
    {
        file_put_contents($this->root . '/content/' . $path, "---\ntitle: Entry\n{$themeLine}---\nBody\n");
    }

    private function install(bool $activate): void
    {
        new PackageInstaller(new GitHubReleaseSource(WonderFixture::http()))->install(
            new InstallRequest(
                $this->root,
                GitHubRepository::fromUrl('https://github.com/' . WonderFixture::REPOSITORY),
                ExtensionType::Theme,
                null,
                EngineVersion::fromBuild('0.1.13'),
                new Platform('linux', 'amd64'),
            ),
            new ThemeDestinationPolicy('content', $activate),
        );
    }

    private function id(): ExtensionId
    {
        $id = ExtensionId::tryFromString(WonderFixture::ID);
        self::assertNotNull($id);

        return $id;
    }

    private function remove(): RemoveResult
    {
        return new ThemeRemover()->remove($this->root, $this->id(), 'content');
    }

    private function removeFails(string $code): InstallException
    {
        $lock = @file_get_contents($this->root . '/yiipress.lock');
        $config = file_get_contents($this->root . '/content/config.yaml');
        try {
            $this->remove();
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame($code, $exception->errorCode, $exception->getMessage());
            self::assertSame($lock, @file_get_contents($this->root . '/yiipress.lock'));
            self::assertSame($config, file_get_contents($this->root . '/content/config.yaml'));
            self::assertSame([], glob($this->root . '/.yiipress/{staging,backup}/*', GLOB_BRACE));

            return $exception;
        }
    }
}
