<?php

declare(strict_types=1);

namespace App\Benchmarks;

use App\Build\BuildProfile;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

final class BuildProfileBench
{
    #[Revs(1000)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchDisabledPhaseSwitches(): void
    {
        $profile = new BuildProfile(false);

        $profile->start('prepare');
        $profile->switchTo('parse content');
        $profile->switchTo('write entries');
        $profile->stop();
    }

    #[Revs(1000)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchEnabledPhaseSwitches(): void
    {
        $profile = new BuildProfile(true);

        $profile->start('prepare');
        $profile->switchTo('parse content');
        $profile->switchTo('write entries');
        $profile->stop();
        $profile->phases();
    }
}
