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
    }

    #[Revs(100)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchHtmlMinification(): void
    {
        OutputMinifier::html($this->html);
    }
}
