<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Packaging;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use YiiPress\Build\ApplicationInfoStamper;

use function dirname;
use function file_get_contents;

final class ApplicationInfoStamperTest extends TestCase
{
    private const string COMMIT = '80964110a7e8b1c2d3e4f5a6b7c8d9e0f1a2b3c4';

    #[Test]
    public function stampsCommitAndBaseVersion(): void
    {
        $stamped = ApplicationInfoStamper::stamp($this->applicationInfo(), self::COMMIT, '0.1.13');

        self::assertStringContainsString("public const string COMMIT = '" . self::COMMIT . "';", $stamped);
        self::assertStringContainsString("public const string BASE_VERSION = '0.1.13';", $stamped);
    }

    #[Test]
    public function leavesBaseVersionEmptyWhenNotGiven(): void
    {
        $stamped = ApplicationInfoStamper::stamp($this->applicationInfo(), self::COMMIT, '');

        self::assertStringContainsString("public const string COMMIT = '" . self::COMMIT . "';", $stamped);
        self::assertStringContainsString("public const string BASE_VERSION = '';", $stamped);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidBaseVersionDataProvider(): iterable
    {
        yield 'v prefix' => ['v0.1.13'];
        yield 'nightly tag' => ['nightly-515-1-80964110a7e8'];
        yield 'two parts' => ['0.1'];
        yield 'quote' => ["0.1.13';"];
        yield 'trailing newline' => ["0.1.13\n"];
    }

    #[Test]
    #[DataProvider('invalidBaseVersionDataProvider')]
    public function rejectsInvalidBaseVersion(string $baseVersion): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('YIIPRESS_BASE_VERSION must be a release version such as 0.1.13.');

        ApplicationInfoStamper::stamp($this->applicationInfo(), self::COMMIT, $baseVersion);
    }

    #[Test]
    public function rejectsSourceWithoutBaseVersionConstant(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to embed the YiiPress base version in src/ApplicationInfo.php.');

        ApplicationInfoStamper::stamp("<?php\npublic const string COMMIT = '';\n", self::COMMIT, '0.1.13');
    }

    #[Test]
    public function rejectsSourceWithoutCommitConstant(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to embed the YiiPress commit in src/ApplicationInfo.php.');

        ApplicationInfoStamper::stamp("<?php\npublic const string BASE_VERSION = '';\n", self::COMMIT, '0.1.13');
    }

    private function applicationInfo(): string
    {
        $contents = file_get_contents(dirname(__DIR__, 3) . '/src/ApplicationInfo.php');
        self::assertIsString($contents);

        return $contents;
    }
}
