<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use YiiPress\RuntimePaths;

use function hash;
use function sys_get_temp_dir;

final class RuntimePathsTest extends TestCase
{
    #[Test]
    public function sourceRuntimePathUsesProjectRuntimeDirectory(): void
    {
        self::assertSame('/site/runtime', RuntimePaths::runtimePath('/site', false));
        self::assertSame('/site/runtime/cache', RuntimePaths::cachePath('/site'));
    }

    #[Test]
    public function packagedRuntimePathUsesProjectScopedTempDirectory(): void
    {
        $projectRoot = '/site';
        $expected = sys_get_temp_dir() . '/yiipress/runtime/' . hash('xxh128', $projectRoot);

        self::assertSame($expected, RuntimePaths::runtimePath($projectRoot, true));
    }
}
