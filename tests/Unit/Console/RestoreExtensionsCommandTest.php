<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Console\RestoreExtensionsCommand;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\ExtensionId;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Platform;
use YiiPress\Tests\Support\FakeHttpClient;
use YiiPress\Tests\Support\WonderFixture;
use Yiisoft\Yii\Console\ExitCode;

use function file_put_contents;
use function gzdecode;
use function gzencode;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class RestoreExtensionsCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-restore-command-test-' . uniqid();
        mkdir($this->root, 0o755, true);
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    public function testReportsEachPackage(): void
    {
        $this->writeLock();

        $tester = $this->tester(EngineVersion::fromBuild('0.1.13'), WonderFixture::http());
        $exitCode = $tester->execute([]);

        self::assertSame(ExitCode::OK, $exitCode, $tester->getDisplay());
        self::assertSame(
            "yiipress/wonder: installed into themes/wonder from the locked archive.\n",
            $tester->getDisplay(),
        );
    }

    public function testReportsIntactAndRepairedPackages(): void
    {
        $this->writeLock();
        $this->tester(EngineVersion::fromBuild('0.1.13'), WonderFixture::http())->execute([]);
        $intact = $this->tester(EngineVersion::fromBuild('0.1.13'), new FakeHttpClient());
        $intact->execute([]);
        unlink($this->root . '/themes/wonder/entry.php');
        $repaired = $this->tester(EngineVersion::fromBuild('0.1.13'), WonderFixture::http());
        $repaired->execute([]);

        self::assertSame("yiipress/wonder: matches yiipress.lock in themes/wonder.\n", $intact->getDisplay());
        self::assertSame("yiipress/wonder: put back entry.php in themes/wonder.\n", $repaired->getDisplay());
    }

    public function testRegeneratedArchiveIsAWarning(): void
    {
        $this->writeLock();
        $regenerated = (string) gzencode((string) gzdecode(WonderFixture::archive()), 1);

        $tester = $this->tester(EngineVersion::fromBuild('0.1.13'), WonderFixture::http($regenerated));
        $exitCode = $tester->execute([]);

        self::assertSame(ExitCode::OK, $exitCode);
        self::assertStringContainsString('GitHub regenerated the tarball', $tester->getDisplay());
        self::assertStringContainsString('installed into themes/wonder', $tester->getDisplay());
    }

    public function testRefusalListsEveryProblemWithItsSolution(): void
    {
        $this->writeLock();
        mkdir($this->root . '/themes/wonder', 0o755, true);
        file_put_contents($this->root . '/themes/wonder/entry.php', 'edited');
        file_put_contents($this->root . '/themes/wonder/notes.txt', 'mine');

        $tester = $this->tester(EngineVersion::fromBuild('0.1.13'), WonderFixture::http());
        $exitCode = $tester->execute([]);

        self::assertSame(ExitCode::DATAERR, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringStartsWith('Nothing was restored', $display);
        self::assertStringContainsString('differ from the locked release: entry.php.', $display);
        self::assertStringContainsString('not part of yiipress/wonder 1.0.0: notes.txt.', $display);
        self::assertStringContainsString('Move your files out of "themes/wonder"', $display);
    }

    public function testNetworkFailureExitsUnavailable(): void
    {
        $this->writeLock();
        $http = new FakeHttpClient()->route(WonderFixture::ARCHIVE_URL, 'Unavailable', 503);

        $exitCode = $this->tester(EngineVersion::fromBuild('0.1.13'), $http)->execute([]);

        self::assertSame(ExitCode::UNAVAILABLE, $exitCode);
    }

    public function testEmptyLockSaysThereIsNothingToRestore(): void
    {
        $tester = $this->tester(EngineVersion::fromBuild('0.1.13'), new FakeHttpClient());

        $exitCode = $tester->execute([]);

        self::assertSame(ExitCode::OK, $exitCode);
        self::assertSame("yiipress.lock lists no extensions; nothing to restore.\n", $tester->getDisplay());
    }

    public function testEngineVersionOverrideWorksOnlyWhenVersionIsUnknown(): void
    {
        $this->writeLock();

        $unknown = $this->tester(EngineVersion::fromBuild(null), WonderFixture::http());
        $known = $this->tester(EngineVersion::fromBuild('0.1.13'), WonderFixture::http());

        self::assertSame(ExitCode::OK, $unknown->execute(['--engine-version' => '0.1.13']), $unknown->getDisplay());
        self::assertSame(ExitCode::USAGE, $known->execute(['--engine-version' => '0.1.13']));
    }

    private function writeLock(): void
    {
        $id = ExtensionId::tryFromString(WonderFixture::ID) ?? self::fail('Bad ID.');
        file_put_contents(
            $this->root . '/yiipress.lock',
            LockFile::empty()->withExtension($id, WonderFixture::locked())->toJson(),
        );
    }

    private function tester(EngineVersion $engine, FakeHttpClient $http): CommandTester
    {
        return new CommandTester(
            new RestoreExtensionsCommand($this->root, $http, $engine, new Platform('linux', 'amd64')),
        );
    }
}
