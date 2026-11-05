<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Console\RestoreExtensionsCommand;
use YiiPress\Console\UpdateThemeCommand;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\ExtensionId;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Platform;
use YiiPress\Tests\Support\FakeHttpClient;
use YiiPress\Tests\Support\WonderFixture;
use Yiisoft\Yii\Console\ExitCode;

use function file_put_contents;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

final class UpdateThemeCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-update-command-test-' . uniqid();
        mkdir($this->root, 0o755, true);
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    public function testReportsUpdate(): void
    {
        $this->writeLock();
        $this->restore();
        $http = WonderFixture::httpWith(['v1.1.0' => WonderFixture::release('1.1.0', "<?php echo \$content;\n")]);

        $tester = $this->tester($http);
        $exitCode = $tester->execute(['id' => WonderFixture::ID]);

        self::assertSame(ExitCode::OK, $exitCode, $tester->getDisplay());
        self::assertSame(
            "Updated yiipress/wonder from 1.0.0 to 1.1.0 (v1.1.0, highest stable release compatible with YiiPress "
            . "0.1.13) in themes/wonder.\n",
            $tester->getDisplay(),
        );
    }

    public function testReportsUpToDate(): void
    {
        $this->writeLock();
        $this->restore();

        $tester = $this->tester(WonderFixture::http());
        $exitCode = $tester->execute(['id' => WonderFixture::ID]);

        self::assertSame(ExitCode::OK, $exitCode, $tester->getDisplay());
        self::assertSame(
            "yiipress/wonder 1.0.0 is up to date: the newest compatible release is 1.0.0.\n",
            $tester->getDisplay(),
        );
    }

    public function testInvalidIdIsUsageError(): void
    {
        $tester = $this->tester(new FakeHttpClient());

        self::assertSame(ExitCode::USAGE, $tester->execute(['id' => 'not an id']));
        self::assertStringContainsString('vendor/name', $tester->getDisplay());
    }

    public function testRefusalIsDataError(): void
    {
        $tester = $this->tester(new FakeHttpClient());

        self::assertSame(ExitCode::DATAERR, $tester->execute(['id' => WonderFixture::ID]));
        self::assertStringContainsString('is not installed', $tester->getDisplay());
        self::assertStringContainsString('get:theme', $tester->getDisplay());
    }

    private function writeLock(): void
    {
        $id = ExtensionId::tryFromString(WonderFixture::ID) ?? self::fail('Bad ID.');
        file_put_contents(
            $this->root . '/yiipress.lock',
            LockFile::empty()->withExtension($id, WonderFixture::locked())->toJson(),
        );
    }

    private function restore(): void
    {
        new CommandTester(new RestoreExtensionsCommand(
            $this->root,
            WonderFixture::http(),
            EngineVersion::fromBuild('0.1.13'),
            new Platform('linux', 'amd64'),
        ))->execute([]);
    }

    private function tester(FakeHttpClient $http): CommandTester
    {
        return new CommandTester(
            new UpdateThemeCommand(
                $this->root,
                $http,
                EngineVersion::fromBuild('0.1.13'),
                new Platform('linux', 'amd64'),
            ),
        );
    }
}
