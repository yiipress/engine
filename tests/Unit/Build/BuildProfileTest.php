<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\BuildProfile;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertArrayHasKey;
use function PHPUnit\Framework\assertGreaterThanOrEqual;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertTrue;

final class BuildProfileTest extends TestCase
{
    public function testDisabledProfileDoesNotRecordPhases(): void
    {
        $profile = new BuildProfile(false);

        $profile->start('prepare');
        $profile->switchTo('parse content');
        $profile->stop();

        assertSame([], $profile->phases());
        assertSame(0.0, $profile->totalSeconds());
    }

    public function testRecordsNamedPhases(): void
    {
        $profile = new BuildProfile(true);

        $profile->start('prepare');
        $profile->switchTo('parse content');
        $profile->stop();

        $phases = $profile->phases();

        assertArrayHasKey('prepare', $phases);
        assertArrayHasKey('parse content', $phases);
        assertGreaterThanOrEqual(0.0, $phases['prepare']);
        assertGreaterThanOrEqual(0.0, $phases['parse content']);
        assertTrue($profile->totalSeconds() >= $phases['prepare'] + $phases['parse content']);
    }
}
