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
use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Platform;
use YiiPress\Extension\Release\GitHubReleaseSource;
use YiiPress\Extension\Release\GitHubRepository;
use YiiPress\Extension\Theme\ThemeDestinationPolicy;
use YiiPress\Extension\Lifecycle\ThemeUpdater;
use YiiPress\Extension\Lifecycle\UpdateResult;
use YiiPress\Tests\Support\FakeHttpClient;
use YiiPress\Tests\Support\WonderFixture;
use YiiPress\Tests\Unit\Extension\Restore\LockedArchiveTest;

use function file_get_contents;
use function file_put_contents;
use function glob;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;
use function str_replace;
use function unlink;

use const GLOB_BRACE;

final class ThemeUpdaterTest extends TestCase
{
    private const string CONFIG = "title: Blog\ntheme: wonder\n";

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-updater-test-' . uniqid();
        mkdir($this->root . '/content', 0o755, true);
        file_put_contents($this->root . '/content/config.yaml', "title: Blog\n");
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    public function testUpdatesToNewestRelease(): void
    {
        $this->installVersion('1.0.0');
        $http = $this->http();

        $result = $this->update($http);

        self::assertTrue($result->updated());
        self::assertSame('1.0.0', $result->previous->version);
        self::assertSame('1.2.0', $result->current->version);
        self::assertSame("<?php // 1.2.0\n", file_get_contents($this->root . '/themes/wonder/entry.php'));
        $locked = $this->locked();
        self::assertSame('v1.2.0', $locked->tag);
        self::assertSame('themes/wonder', $locked->destination);
        self::assertSame('wonder', $locked->name);
        self::assertSame(self::CONFIG, file_get_contents($this->root . '/content/config.yaml'));
        self::assertSame([], glob($this->root . '/.yiipress/{staging,backup}/*', GLOB_BRACE));
        self::assertFileDoesNotExist($this->root . '/.yiipress/journal.json');
    }

    public function testExplicitVersionDowngrades(): void
    {
        $this->installVersion('1.2.0');

        $result = $this->update($this->http(), '1.0.0');

        self::assertSame('1.0.0', $result->current->version);
        self::assertSame(WonderFixture::locked()->files, $this->locked()->files);
        self::assertSame(self::CONFIG, file_get_contents($this->root . '/content/config.yaml'));
    }

    public function testNothingToDoAtNewestRelease(): void
    {
        $this->installVersion('1.2.0');
        $lock = file_get_contents($this->root . '/yiipress.lock');
        $http = $this->http();

        $result = $this->update($http);

        self::assertFalse($result->updated());
        self::assertSame($lock, file_get_contents($this->root . '/yiipress.lock'));
        self::assertNotContains(
            'https://codeload.github.com/' . WonderFixture::REPOSITORY . '/tar.gz/refs/tags/v1.2.0',
            $http->requests,
        );
    }

    public function testDoesNotDowngradeWithoutExplicitVersion(): void
    {
        $this->installVersion('1.2.0');

        // YiiPress 0.1.13 is too old for 1.2.0 here, so the newest compatible release is 1.1.0.
        $result = $this->update($this->http('>=0.2.0'));

        self::assertFalse($result->updated());
        self::assertSame('1.2.0', $this->locked()->version);
    }

    public function testRefusesLocalChangesBeforeDownloading(): void
    {
        $this->installVersion('1.0.0');
        file_put_contents($this->root . '/themes/wonder/entry.php', 'edited');
        file_put_contents($this->root . '/themes/wonder/mine.php', 'mine');
        $http = $this->http();

        $exception = $this->updateFails('local_changes', $http);

        self::assertStringContainsString('entry.php', $exception->getMessage());
        self::assertStringContainsString('mine.php', $exception->getMessage());
        self::assertSame([], $http->requests);
        self::assertSame('edited', file_get_contents($this->root . '/themes/wonder/entry.php'));
        self::assertSame('1.0.0', $this->locked()->version);
    }

    public function testMissingFilesPointToRestore(): void
    {
        $this->installVersion('1.0.0');
        unlink($this->root . '/themes/wonder/entry.php');

        $exception = $this->updateFails('local_changes', $this->http());

        self::assertStringContainsString('restore:extensions', (string) $exception->getSolution());
    }

    public function testRefusesThemeWhoseDirectoryIsGone(): void
    {
        $this->installVersion('1.0.0');
        DirectoryRemover::remove($this->root . '/themes/wonder');

        $exception = $this->updateFails('local_changes', $this->http());

        self::assertStringContainsString('the directory is missing', $exception->getMessage());
    }

    public function testRefusesThemeThatIsNotInstalled(): void
    {
        $this->updateFails('not_installed', $this->http());
    }

    public function testRefusesLockEntryThatIsNotATheme(): void
    {
        $locked = LockedArchiveTest::with(
            WonderFixture::locked(),
            ['type' => 'processor', 'name' => null, 'fallback' => null],
        );
        file_put_contents(
            $this->root . '/yiipress.lock',
            LockFile::empty()->withExtension(ExtensionId::tryFromString(WonderFixture::ID), $locked)->toJson(),
        );

        $this->updateFails('extension_not_theme', $this->http());
    }

    public function testRefusesReleaseOfAnotherExtension(): void
    {
        $this->installVersion('1.0.0');
        $http = WonderFixture::httpWith([
            'v1.1.0' => WonderFixture::release('1.1.0', "<?php // other\n", 'someone/wonder'),
        ]);

        $this->updateFails('manifest_mismatch', $http);

        self::assertSame('1.0.0', $this->locked()->version);
        self::assertSame(WonderFixture::locked()->files, $this->locked()->files);
    }

    private function http(string $requires120 = '>=0.1.0'): FakeHttpClient
    {
        $newest = WonderFixture::release('1.2.0', "<?php // 1.2.0\n");
        $newest['manifest'] = str_replace('">=0.1.0"', '"' . $requires120 . '"', $newest['manifest']);

        return WonderFixture::httpWith([
            'v1.1.0' => WonderFixture::release('1.1.0', "<?php // 1.1.0\n"),
            'v1.2.0' => $newest,
        ]);
    }

    private function installVersion(string $version): void
    {
        new PackageInstaller(new GitHubReleaseSource($this->http()))->install(
            new InstallRequest(
                $this->root,
                GitHubRepository::fromUrl('https://github.com/' . WonderFixture::REPOSITORY),
                ExtensionType::Theme,
                $version,
                EngineVersion::fromBuild('0.1.13'),
                new Platform('linux', 'amd64'),
            ),
            new ThemeDestinationPolicy(),
        );
    }

    private function update(FakeHttpClient $http, ?string $version = null): UpdateResult
    {
        $http->requests = [];

        return new ThemeUpdater(new GitHubReleaseSource($http))->update(
            $this->root,
            ExtensionId::tryFromString(WonderFixture::ID),
            $version,
            EngineVersion::fromBuild('0.1.13'),
            new Platform('linux', 'amd64'),
        );
    }

    private function updateFails(string $code, FakeHttpClient $http): InstallException
    {
        try {
            $this->update($http);
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame($code, $exception->errorCode, $exception->getMessage());
            self::assertSame([], glob($this->root . '/.yiipress/{staging,backup}/*', GLOB_BRACE));

            return $exception;
        }
    }

    private function locked(): LockedExtension
    {
        $locked = LockFile::read($this->root . '/yiipress.lock')->get(ExtensionId::tryFromString(WonderFixture::ID));
        self::assertNotNull($locked);

        return $locked;
    }
}
