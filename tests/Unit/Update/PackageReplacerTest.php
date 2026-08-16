<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Update;

use Closure;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use YiiPress\Update\PackageReplacer;

use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;
use function uniqid;

final class PackageReplacerTest extends TestCase
{
    #[Test]
    public function replacesPackageAfterApplicationShutdownOnPosix(): void
    {
        $directory = sys_get_temp_dir() . '/yiipress-replacer-' . uniqid('', true);
        mkdir($directory);
        $temporaryPath = $directory . '/update';
        $targetPath = $directory . '/yiipress';
        file_put_contents($temporaryPath, 'new');
        file_put_contents($targetPath, 'old');
        $replacement = null;

        try {
            $registrar = static function (Closure $callback) use (&$replacement): void {
                $replacement = $callback;
            };
            new PackageReplacer('Linux', $registrar)->replace($temporaryPath, $targetPath);

            self::assertSame('old', file_get_contents($targetPath));
            self::assertFileExists($temporaryPath);
            self::assertInstanceOf(Closure::class, $replacement);

            $replacement();
            self::assertSame('new', file_get_contents($targetPath));
            self::assertFileDoesNotExist($temporaryPath);
        } finally {
            @unlink($temporaryPath);
            @unlink($temporaryPath . '.sh');
            @unlink($targetPath);
            @rmdir($directory);
        }
    }

    #[Test]
    public function rejectsUnsafeWindowsHelperPath(): void
    {
        $this->expectExceptionMessage('paths containing %');

        new PackageReplacer('Windows')->replace('C:\\Temp\\%update%', 'C:\\YiiPress\\yiipress.exe');
    }

    #[Test]
    public function startsWindowsHelperThroughDetachedCommandInterpreter(): void
    {
        $directory = sys_get_temp_dir() . '/yiipress-replacer-' . uniqid('', true);
        mkdir($directory);
        $temporaryPath = $directory . '/update package';
        $targetPath = $directory . '/yiipress.exe';
        $command = null;
        $processStarter = static function (array $value) use (&$command): bool {
            $command = $value;

            return true;
        };

        try {
            new PackageReplacer('Windows', processStarter: $processStarter)->replace($temporaryPath, $targetPath);

            self::assertSame(
                [
                    'cmd.exe',
                    '/d',
                    '/c',
                    'start',
                    '',
                    '/b',
                    'cmd.exe',
                    '/d',
                    '/c',
                    'call',
                    $temporaryPath . '.cmd',
                ],
                $command,
            );
            self::assertStringContainsString(
                "move /Y \"$temporaryPath\" \"$targetPath\"",
                (string) file_get_contents($temporaryPath . '.cmd'),
            );
        } finally {
            @unlink($temporaryPath . '.cmd');
            @rmdir($directory);
        }
    }

    #[Test]
    public function removesWindowsHelperWhenItCannotBeStarted(): void
    {
        $directory = sys_get_temp_dir() . '/yiipress-replacer-' . uniqid('', true);
        mkdir($directory);
        $temporaryPath = $directory . '/update';
        $scriptPath = $temporaryPath . '.cmd';

        try {
            new PackageReplacer(
                'Windows',
                processStarter: static fn(array $command): bool => false,
            )->replace($temporaryPath, $directory . '/yiipress.exe');
            self::fail('Expected the Windows helper launch to fail.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Could not start the Windows update helper.', $exception->getMessage());
            self::assertFileDoesNotExist($scriptPath);
        } finally {
            @unlink($scriptPath);
            @rmdir($directory);
        }
    }

    #[Test]
    public function reportsDeferredReplacementFailureWithoutThrowing(): void
    {
        $replacement = null;
        $failure = null;
        $registrar = static function (Closure $callback) use (&$replacement): void {
            $replacement = $callback;
        };
        $failureHandler = static function (string $message) use (&$failure): void {
            $failure = $message;
        };
        new PackageReplacer('Linux', $registrar, $failureHandler)->replace(
            '/missing/update',
            '/missing/yiipress',
        );
        self::assertInstanceOf(Closure::class, $replacement);

        $replacement();

        self::assertSame('Could not replace /missing/yiipress. Check its permissions.', $failure);
    }
}
