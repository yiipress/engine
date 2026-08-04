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
        self::assertMatchesRegularExpression('/^(?:\d+\.\d+\.\d+|[0-9a-f]{40}|unknown)/', ApplicationInfo::version());
        self::assertSame('', ApplicationInfo::COMMIT);
        self::assertStringNotContainsString('no-version-set', ApplicationInfo::version());
    }
}
