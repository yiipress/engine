<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use YiiPress\Processor\LatexMath\LatexMathProcessor;

final class LatexMathProcessorBench
{
    private LatexMathProcessor $processor;
    private string $content;

    public function __construct()
    {
        $this->processor = new LatexMathProcessor();
        $this->content = str_repeat('<p>Inline <x-equation>x + y</x-equation>.</p>', 100);
    }

    #[Revs(1_000)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchHeadAssetDetection(): void
    {
        $this->processor->headAssets($this->content);
    }
}
