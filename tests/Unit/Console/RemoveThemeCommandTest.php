<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Console\RemoveThemeCommand;
use YiiPress\Console\RestoreExtensionsCommand;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\ExtensionId;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Platform;
use YiiPress\Tests\Support\WonderFixture;
use Yiisoft\Yii\Console\ExitCode;

use function file_put_contents;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

final class RemoveThemeCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-remove-command-test-' . uniqid();
        mkdir($this->root . '/site', 0o755, true);
        file_put_contents($this->root . '/site/config.yaml', "title: Blog\nlanguages: [en]\n");
        $id = ExtensionId::tryFromString(WonderFixture::ID) ?? self::fail('Bad ID.');
        file_put_contents(
            $this->root . '/yiipress.lock',
            LockFile::empty()->withExtension($id, WonderFixture::locked())->toJson(),
        );
        new CommandTester(new RestoreExtensionsCommand(
            $this->root,
            WonderFixture::http(),
            EngineVersion::fromBuild('0.1.13'),
            new Platform('linux', 'amd64'),
        ))->execute([]);
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    public function testReportsRemoval(): void
    {
        $tester = new CommandTester(new RemoveThemeCommand($this->root));
        $exitCode = $tester->execute(['id' => WonderFixture::ID, '--content-dir' => 'site']);

        self::assertSame(ExitCode::OK, $exitCode, $tester->getDisplay());
        self::assertSame(
            "Removed yiipress/wonder 1.0.0: deleted themes/wonder and its entry in yiipress.lock.\n",
            $tester->getDisplay(),
        );
        self::assertDirectoryDoesNotExist($this->root . '/themes/wonder');
    }

    public function testThemeInUseIsDataErrorWithInstructions(): void
    {
        file_put_contents($this->root . '/site/config.yaml', "title: Blog\nlanguages: [en]\ntheme: wonder\n");

        $tester = new CommandTester(new RemoveThemeCommand($this->root));
        $exitCode = $tester->execute(['id' => WonderFixture::ID, '--content-dir' => 'site']);

        self::assertSame(ExitCode::DATAERR, $exitCode);
        self::assertStringContainsString(
            'Theme "wonder" is still selected by site/config.yaml.',
            $tester->getDisplay(),
        );
        self::assertStringContainsString('Set "theme" in config.yaml to another theme', $tester->getDisplay());
        self::assertDirectoryExists($this->root . '/themes/wonder');
    }

    public function testInvalidIdIsUsageError(): void
    {
        $tester = new CommandTester(new RemoveThemeCommand($this->root));

        self::assertSame(ExitCode::USAGE, $tester->execute(['id' => 'Wonder']));
        self::assertDirectoryExists($this->root . '/themes/wonder');
    }
}
