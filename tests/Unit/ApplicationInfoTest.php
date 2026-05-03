<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit;

use YiiPress\ApplicationInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApplicationInfoTest extends TestCase
{
    #[Test]
    public function versionIsUserFacingReleaseVersion(): void
    {
        self::assertSame('YiiPress', ApplicationInfo::NAME);
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', ApplicationInfo::version());
        self::assertStringNotContainsString('no-version-set', ApplicationInfo::version());
    }
}
