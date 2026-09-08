<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use YiiPress\Build\OutputMinifier;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

final class OutputMinifierBench
{
    private string $html;

    private string $ordinaryDivs;

    private string $manyProtectedBlocks;

    private string $incompleteMarkup;

    public function __construct()
    {
        $block = <<<'HTML'
            <article>
                <h2>Generated output</h2>
                <p>YiiPress keeps generated pages compact while preserving code.</p>
                <pre><code>Line 1
                    Line 2</code></pre>
                <script>
                    const message = "  keep script spacing  ";
                </script>
            </article>
            HTML;

        $this->html = str_repeat($block, 100);
        $this->manyProtectedBlocks = str_repeat($block, 1000);
        $this->incompleteMarkup = str_repeat('<p title="  attribute spacing  ">  Some   text  </p>', 100) . '<unfinished  attribute="  value';
        $this->ordinaryDivs = str_repeat('<div aria-hidden="true" class="decoration">  Ordinary   content  </div>', 100);
    }

    #[Revs(10)]
    #[Iterations(5)]
    #[Warmup(1)]
    public function benchIncompleteMarkup(): void
    {
        OutputMinifier::html($this->incompleteMarkup);
    }

    #[Revs(100)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchHtmlMinification(): void
    {
        OutputMinifier::html($this->html);
    }

    #[Revs(10)]
    #[Iterations(5)]
    #[Warmup(1)]
    public function benchOrdinaryDivs(): void
    {
        OutputMinifier::html($this->ordinaryDivs);
    }

    #[Revs(10)]
    #[Iterations(5)]
    #[Warmup(1)]
    public function benchManyProtectedBlocks(): void
    {
        OutputMinifier::html($this->manyProtectedBlocks);
    }
}
