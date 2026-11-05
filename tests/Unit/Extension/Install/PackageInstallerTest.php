<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Install;

use PHPUnit\Framework\TestCase;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Install\InstallRequest;
use YiiPress\Extension\Install\InstallResult;
use YiiPress\Extension\Install\PackageInstaller;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Platform;
use YiiPress\Extension\Release\GitHubReleaseSource;
use YiiPress\Extension\Release\GitHubRepository;
use YiiPress\Extension\Theme\ThemeDestinationPolicy;
use YiiPress\Extension\Transaction\MutationLock;
use YiiPress\Tests\Support\FakeHttpClient;
use YiiPress\Tests\Unit\Extension\Release\ReleaseSelectorTest;

use function array_keys;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function hash;
use function is_dir;
use function mkdir;
use function scandir;
use function str_replace;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class PackageInstallerTest extends TestCase
{
    private const string REPO = 'yiipress/theme-wonder';
    private const string COMMIT = 'a42fc8f';

    private string $root;
    private string $archive;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-installer-test-' . uniqid();
        mkdir($this->root . '/content', 0o755, true);
        file_put_contents($this->root . '/content/config.yaml', "title: Blog\ntheme: minimal # site theme\n");
        $this->archive = (string) file_get_contents(dirname(__DIR__, 3) . '/Support/Data/extension/wonder-v1.0.0.tar.gz');
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    public function testInstallsActivatesAndLocks(): void
    {
        $result = $this->install($this->http(ReleaseSelectorTest::manifest('1.0.0', '>=0.1.0')));

        self::assertSame('themes/wonder', $result->locked->destination);
        self::assertSame('content/config.yaml', $result->activated);
        self::assertStringStartsWith('<?php', (string) file_get_contents($this->root . '/themes/wonder/entry.php'));
        self::assertFileExists($this->root . '/themes/wonder/assets/style.css');
        self::assertFileDoesNotExist($this->root . '/themes/wonder/extension.yaml');
        self::assertSame(
            "title: Blog\ntheme: wonder # site theme\n",
            file_get_contents($this->root . '/content/config.yaml'),
        );

        $locked = LockFile::read($this->root . '/yiipress.lock')->get($result->selected->manifest->id);
        self::assertNotNull($locked);
        self::assertSame('https://github.com/' . self::REPO, $locked->source);
        self::assertSame('v1.0.0', $locked->tag);
        self::assertStringStartsWith(self::COMMIT, $locked->commit);
        self::assertSame(hash('sha256', $this->archive), $locked->archiveSha256);
        self::assertSame(['assets/style.css', 'entry.php'], array_keys($locked->files));
        self::assertSame('wonder', $locked->name);
        self::assertFileDoesNotExist($this->root . '/.yiipress/journal.json');
        self::assertSame([], glob($this->root . '/.yiipress/staging/*'));
        self::assertSame([], glob($this->root . '/.yiipress/backup/*'));
    }

    public function testSameInstallGivesSameLockBytes(): void
    {
        $this->install($this->http(ReleaseSelectorTest::manifest('1.0.0', '>=0.1.0')));
        $first = file_get_contents($this->root . '/yiipress.lock');
        DirectoryRemover::remove($this->root . '/themes');
        unlink($this->root . '/yiipress.lock');

        $this->install($this->http(ReleaseSelectorTest::manifest('1.0.0', '>=0.1.0')));

        self::assertSame($first, file_get_contents($this->root . '/yiipress.lock'));
    }

    public function testArchiveManifestMustMatchSelectedOne(): void
    {
        $tagManifest = str_replace('yiipress/wonder', 'yiipress/other', ReleaseSelectorTest::manifest('1.0.0'));

        $this->assertFailsUnchanged('manifest_mismatch', $this->http($tagManifest));
    }

    public function testExistingDestinationLeavesProjectUnchanged(): void
    {
        mkdir($this->root . '/themes/wonder', 0o755, true);

        $this->assertFailsUnchanged('destination_exists', $this->http(ReleaseSelectorTest::manifest('1.0.0')));
    }

    public function testMissingConfigIsCheckedBeforeInstalling(): void
    {
        $http = $this->http(ReleaseSelectorTest::manifest('1.0.0'));

        $this->assertFailsUnchanged('config_missing', $http, new ThemeDestinationPolicy('missing'));
    }

    public function testRefusesWhileAnotherCommandHoldsTheLock(): void
    {
        $lock = MutationLock::acquire($this->root);
        try {
            $this->assertFailsUnchanged('mutation_locked', $this->http(ReleaseSelectorTest::manifest('1.0.0')));
        } finally {
            $lock->release();
        }
    }

    public function testRecoversInterruptedOperationFirst(): void
    {
        mkdir($this->root . '/.yiipress');
        file_put_contents(
            $this->root . '/.yiipress/journal.json',
            '{"journal": 1, "tx": "0123456789abcdef", "operation": "install", "state": "committed", "steps": []}',
        );

        $result = $this->install($this->http(ReleaseSelectorTest::manifest('1.0.0')));

        self::assertNotNull($result->recovered);
        self::assertFileExists($this->root . '/themes/wonder/entry.php');
    }

    private function assertFailsUnchanged(
        string $code,
        FakeHttpClient $http,
        ThemeDestinationPolicy $policy = new ThemeDestinationPolicy(),
    ): void {
        $themesBefore = is_dir($this->root . '/themes') ? scandir($this->root . '/themes') : null;
        try {
            $this->install($http, $policy);
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame($code, $exception->errorCode, $exception->getMessage());
        }

        self::assertSame($themesBefore, is_dir($this->root . '/themes') ? scandir($this->root . '/themes') : null);
        self::assertFileDoesNotExist($this->root . '/yiipress.lock');
        self::assertSame(
            "title: Blog\ntheme: minimal # site theme\n",
            file_get_contents($this->root . '/content/config.yaml'),
        );
        self::assertSame([], glob($this->root . '/.yiipress/staging/*'));
        self::assertFileDoesNotExist($this->root . '/.yiipress/journal.json');
    }

    private function http(string $tagManifest): FakeHttpClient
    {
        return new FakeHttpClient()->gitHub(self::REPO, [
            'v1.0.0' => ['manifest' => $tagManifest, 'archive' => $this->archive],
        ]);
    }

    private function install(
        FakeHttpClient $http,
        ThemeDestinationPolicy $policy = new ThemeDestinationPolicy(),
    ): InstallResult {
        return new PackageInstaller(new GitHubReleaseSource($http))->install(
            new InstallRequest(
                $this->root,
                GitHubRepository::fromUrl('https://github.com/' . self::REPO),
                ExtensionType::Theme,
                null,
                EngineVersion::fromBuild('0.1.13'),
                new Platform('linux', 'amd64'),
            ),
            $policy,
        );
    }
}
