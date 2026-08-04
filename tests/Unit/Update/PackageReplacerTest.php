<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Update;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use YiiPress\Update\PackageReplacer;

final class PackageReplacerTest extends TestCase
{
    #[Test]
    public function atomicallyReplacesPackageOnPosix(): void
    {
        $directory = sys_get_temp_dir() . '/yiipress-replacer-' . uniqid('', true);
        mkdir($directory);
        $temporaryPath = $directory . '/update';
        $targetPath = $directory . '/yiipress';
        file_put_contents($temporaryPath, 'new');
        file_put_contents($targetPath, 'old');

        try {
            (new PackageReplacer('Linux'))->replace($temporaryPath, $targetPath);

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
}
