<?php

declare(strict_types=1);

namespace App\Benchmarks;

use App\Highlighter\SyntaxHighlighter;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use RuntimeException;

use function extension_loaded;
use function file_exists;

#[BeforeMethods('setUp')]
final class SyntaxHighlighterBench
{
    private SyntaxHighlighter $highlighter;
    private string $plainHtml;
    private string $singleBlockHtml;
    private string $multiBlockHtml;

    public function setUp(): void
    {
        if (!extension_loaded('ffi')) {
            throw new RuntimeException('ext-ffi is required for SyntaxHighlighterBench.');
        }

        if (!file_exists('/usr/local/lib/libyiipress_highlighter.so')) {
            throw new RuntimeException('libyiipress_highlighter.so is required for SyntaxHighlighterBench.');
        }

        $this->highlighter = new SyntaxHighlighter();
        $this->plainHtml = '<p>Regular paragraph.</p>';
        $this->singleBlockHtml = '<pre><code class="language-php">&lt;?php echo &quot;hello&quot;;</code></pre>';
        $this->multiBlockHtml = '<p>Intro</p>'
            . '<pre><code class="language-php">&lt;?php echo 1;</code></pre>'
            . '<pre><code class="language-yaml">title: Hello</code></pre>'
            . '<pre><code class="language-json">{&quot;enabled&quot;: true}</code></pre>'
            . '<pre><code class="language-bash">echo hello</code></pre>'
            . '<pre><code class="language-sql">SELECT 1;</code></pre>'
            . '<pre><code class="language-rust">fn main() { println!(&quot;hi&quot;); }</code></pre>'
            . '<pre><code class="language-javascript">console.log(&quot;hi&quot;);</code></pre>'
            . '<pre><code class="language-html">&lt;div&gt;hello&lt;/div&gt;</code></pre>';
    }

    #[Revs(10000)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchSkipPlainHtml(): void
    {
        $this->highlighter->highlight($this->plainHtml);
    }

    #[Revs(2000)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchHighlightSingleBlock(): void
    {
        $this->highlighter->highlight($this->singleBlockHtml);
    }

    #[Revs(500)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchHighlightMultipleBlocks(): void
    {
        $this->highlighter->highlight($this->multiBlockHtml);
    }
}
