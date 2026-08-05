<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit;

use ReflectionMethod;
use YiiPress\ApplicationInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApplicationInfoTest extends TestCase
{
    #[Test]
    public function versionIsUserFacingReleaseVersion(): void
    {
        self::assertSame('YiiPress', ApplicationInfo::NAME);
        self::assertMatchesRegularExpression('/^(?:\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?|[0-9a-f]{40}|unknown)$/', ApplicationInfo::version());
        self::assertSame('', ApplicationInfo::COMMIT);
        self::assertSame('unknown', ApplicationInfo::VERSION);
        self::assertStringNotContainsString('no-version-set', ApplicationInfo::version());
    }

    #[Test]
    public function developmentAliasFallsBackToExactReference(): void
    {
        $method = new ReflectionMethod(ApplicationInfo::class, 'resolveVersion');
        $reference = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

        self::assertSame($reference, $method->invoke(null, '1.0.x-dev', $reference));
        self::assertSame($reference, $method->invoke(null, 'dev-master', $reference));
        self::assertSame('1.2.3', $method->invoke(null, '1.2.3', $reference));
    }

    #[Test]
    public function missingComposerMetadataUsesSafeFallback(): void
    {
        $method = new ReflectionMethod(ApplicationInfo::class, 'resolveVersion');

        self::assertSame(ApplicationInfo::VERSION, $method->invoke(null, null, null));
    }

    #[Test]
    public function packagedCommitTakesPrecedenceOverStaleReleaseMetadata(): void
    {
        $method = new ReflectionMethod(ApplicationInfo::class, 'resolveVersion');
        self::assertSame(
            'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            $method->invoke(
                null,
                '1.0.0',
                'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            ),
        );
    }
}
