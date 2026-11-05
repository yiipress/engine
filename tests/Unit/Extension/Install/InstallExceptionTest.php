<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Install;

use PHPUnit\Framework\TestCase;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Manifest\InvalidManifestException;
use YiiPress\Extension\Manifest\ManifestError;
use Yiisoft\Yii\Console\ExitCode;

final class InstallExceptionTest extends TestCase
{
    public function testUsesDataErrorExitCodeByDefault(): void
    {
        $exception = new InstallException('archive_link', 'The entry is a link.');

        self::assertSame('archive_link', $exception->errorCode);
        self::assertSame(ExitCode::DATAERR, $exception->getCode());
        self::assertSame('The entry is a link.', $exception->getMessage());
    }

    public function testWrapsInvalidManifestWithAllErrors(): void
    {
        $manifest = new InvalidManifestException([
            new ManifestError('required', 'id', 'Field "id" is required.'),
            new ManifestError('required', 'name', 'Field "name" is required.'),
        ]);

        $exception = InstallException::fromInvalidManifest($manifest);

        self::assertSame('manifest_invalid', $exception->errorCode);
        self::assertStringContainsString('Field "id" is required.', $exception->getMessage());
        self::assertStringContainsString('Field "name" is required.', $exception->getMessage());
        self::assertSame($manifest, $exception->getPrevious());
    }
}
