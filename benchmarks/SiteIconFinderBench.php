<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use PhpBench\Attributes as Bench;
use RuntimeException;
use YiiPress\Content\Parser\SiteIconFinder;

#[Bench\BeforeMethods('setUp')]
#[Bench\AfterMethods('tearDown')]
final class SiteIconFinderBench
{
    private string $contentDir;

    public function setUp(): void
    {
        $this->contentDir = $this->createTempDirectory();
        file_put_contents($this->contentDir . '/icon.svg', '<svg/>');
    }

    public function tearDown(): void
    {
        unlink($this->contentDir . '/icon.svg');
        rmdir($this->contentDir);
    }

    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    public function benchFindIcon(): void
    {
        (new SiteIconFinder())->find($this->contentDir);
    }

    private function createTempDirectory(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $path = sys_get_temp_dir() . '/yiipress-icon-bench-' . bin2hex(random_bytes(16));
            if (@mkdir($path, 0o700)) {
                return $path;
            }
        }

        throw new RuntimeException('Could not create benchmark temp directory.');
    }
}
