<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Console\GetThemeCommand;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\Platform;
use YiiPress\Tests\Support\FakeHttpClient;
use YiiPress\Tests\Unit\Extension\Release\ReleaseSelectorTest;
use Yiisoft\Yii\Console\ExitCode;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

final class GetThemeCommandTest extends TestCase
{
    private const string URL = 'https://github.com/yiipress/theme-wonder';

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-get-theme-test-' . uniqid();
        mkdir($this->root . '/site', 0o755, true);
        file_put_contents($this->root . '/site/config.yaml', "title: Blog\n");
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    public function testInstallsAndReportsSelection(): void
    {
        $tester = $this->tester(EngineVersion::fromBuild('0.1.13'));

        $exitCode = $tester->execute(['--url' => self::URL, '--content-dir' => 'site']);

        self::assertSame(ExitCode::OK, $exitCode, $tester->getDisplay());
        self::assertSame(
            "Selected Wonder 1.0.0 (v1.0.0), highest stable release compatible with YiiPress 0.1.13.\n"
            . "Installed yiipress/wonder into themes/wonder.\n"
            . "Activated: \"theme\" is now \"wonder\" in site/config.yaml.\n",
            $tester->getDisplay(),
        );
        self::assertSame("title: Blog\ntheme: wonder\n", file_get_contents($this->root . '/site/config.yaml'));
    }

    public function testNoActivateLeavesConfigAlone(): void
    {
        $tester = $this->tester(EngineVersion::fromBuild('0.1.13'));

        $exitCode = $tester->execute(['--url' => self::URL, '--version' => '1.0.0', '--no-activate' => true]);

        self::assertSame(ExitCode::OK, $exitCode, $tester->getDisplay());
        self::assertStringContainsString('(v1.0.0), the requested version.', $tester->getDisplay());
        self::assertStringContainsString('Not activated. Set "theme: wonder" in config.yaml', $tester->getDisplay());
        self::assertSame("title: Blog\n", file_get_contents($this->root . '/site/config.yaml'));
        self::assertFileExists($this->root . '/themes/wonder/entry.php');
    }

    public function testEngineVersionOverrideWorksOnlyWhenVersionIsUnknown(): void
    {
        $tester = $this->tester(EngineVersion::fromBuild(null));

        $exitCode = $tester->execute([
            '--url' => self::URL,
            '--engine-version' => '0.1.13',
            '--no-activate' => true,
        ]);

        self::assertSame(ExitCode::OK, $exitCode, $tester->getDisplay());
        self::assertStringContainsString('compatible with YiiPress 0.1.13.', $tester->getDisplay());
    }

    public function testEngineVersionOverrideIsRefusedForKnownVersion(): void
    {
        $tester = $this->tester(EngineVersion::fromBuild('0.1.13'));

        $exitCode = $tester->execute(['--url' => self::URL, '--engine-version' => '0.2.0']);

        self::assertSame(ExitCode::USAGE, $exitCode);
        self::assertStringContainsString('only be used when the YiiPress version is unknown', $tester->getDisplay());
    }

    public function testUnknownEngineVersionNamesTheOverride(): void
    {
        $tester = $this->tester(EngineVersion::fromBuild(null));

        $exitCode = $tester->execute(['--url' => self::URL]);

        self::assertSame(ExitCode::DATAERR, $exitCode);
        self::assertStringContainsString('--engine-version=X.Y.Z', $tester->getDisplay());
    }

    public function testErrorIsOneFriendlyMessage(): void
    {
        $tester = $this->tester(EngineVersion::fromBuild('0.1.13'));

        $exitCode = $tester->execute(['--url' => 'https://gitlab.com/yiipress/theme-wonder']);

        self::assertSame(ExitCode::DATAERR, $exitCode);
        self::assertStringNotContainsString('Exception', $tester->getDisplay());
        self::assertStringContainsString('gitlab.com', $tester->getDisplay());
    }

    public function testUrlIsRequired(): void
    {
        $tester = $this->tester(EngineVersion::fromBuild('0.1.13'));

        self::assertSame(ExitCode::USAGE, $tester->execute([]));
    }

    private function tester(EngineVersion $engine): CommandTester
    {
        $archive = (string) file_get_contents(dirname(__DIR__, 2) . '/Support/Data/extension/wonder-v1.0.0.tar.gz');
        $http = new FakeHttpClient()->gitHub('yiipress/theme-wonder', [
            'v1.0.0' => ['manifest' => ReleaseSelectorTest::manifest('1.0.0', '>=0.1.0'), 'archive' => $archive],
        ]);

        return new CommandTester(new GetThemeCommand($this->root, $http, $engine, new Platform('linux', 'amd64')));
    }
}
