<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use YiiPress\Build\ContentAssetCopier;

#[Iterations(5)]
#[Revs(1)]
#[Warmup(1)]
final class ContentAssetCopierBench
{
    public function benchDiscoverAssetsAmongTenThousandEntries(): void
    {
        new ContentAssetCopier()->mappings(__DIR__ . '/data/content');
    }
}
