<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Update;

use Closure;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use YiiPress\Update\PackageReplacer;

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
            (new PackageReplacer('Linux', $registrar))->replace($temporaryPath, $targetPath);

            self::assertSame('old', file_get_contents($targetPath));
            self::assertFileExists($temporaryPath);
            self::assertInstanceOf(Closure::class, $replacement);

            $replacement();

            self::assertSame('new', file_get_contents($targetPath));
            self::assertFileDoesNotExist($temporaryPath);
        } finally {
            @unlink($temporaryPath);
            @unlink($targetPath);
            @rmdir($directory);
        }
    }

    #[Test]
    public function rejectsUnsafeWindowsHelperPath(): void
    {
        $this->expectExceptionMessage('paths containing %');

        (new PackageReplacer('Windows'))->replace('C:\\Temp\\%update%', 'C:\\YiiPress\\yiipress.exe');
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
        (new PackageReplacer('Linux', $registrar, $failureHandler))->replace(
            '/missing/update',
            '/missing/yiipress',
        );
        self::assertInstanceOf(Closure::class, $replacement);

        $replacement();

        self::assertSame('Could not replace /missing/yiipress. Check its permissions.', $failure);
    }
}
