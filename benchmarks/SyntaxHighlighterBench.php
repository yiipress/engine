<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use YiiPress\Highlighter;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use RuntimeException;

use function extension_loaded;

#[BeforeMethods('setUp')]
final class SyntaxHighlighterBench
{
    private ?Highlighter $highlighter = null;
    private string $plainHtml = '';
    private string $rawPhpCode = '';
    private string $singleBlockHtml = '';
    private string $multiBlockHtml = '';

    public function setUp(): void
    {
        if (!extension_loaded('highlighter')) {
            throw new RuntimeException('ext-highlighter is required for SyntaxHighlighterBench.');
        }

        $this->highlighter = new Highlighter();
        $this->plainHtml = '<p>Regular paragraph.</p>';
        $this->rawPhpCode = 'echo "hello";';
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
        $this->highlighter()->highlightHtml($this->plainHtml);
    }

    #[Revs(2000)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchHighlightSingleBlock(): void
    {
        $this->highlighter()->highlightHtml($this->singleBlockHtml);
    }

    #[Revs(2000)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchHighlightRawCode(): void
    {
        $this->highlighter()->highlight($this->rawPhpCode, 'php');
    }

    #[Revs(500)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchHighlightMultipleBlocks(): void
    {
        $this->highlighter()->highlightHtml($this->multiBlockHtml);
    }

    private function highlighter(): Highlighter
    {
        return $this->highlighter ??= new Highlighter();
    }
}
