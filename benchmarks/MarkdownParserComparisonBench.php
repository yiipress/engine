<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use RuntimeException;
use YiiPress\Render\MarkdownRenderer;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

use function class_exists;
use function str_repeat;

#[BeforeMethods('setUp')]
final class MarkdownParserComparisonBench
{
    private const string PARSER_MDPARSER = 'mdparser';

    private MarkdownRenderer $markdownRenderer;
    private string $shortMarkdown;
    private string $mediumMarkdown;
    private string $largeMarkdown;

    /**
     * @param array{parser?: string} $params
     */
    public function setUp(array $params = []): void
    {
        $this->markdownRenderer = new MarkdownRenderer();

        $this->shortMarkdown = "# Hello\n\nA short paragraph with **bold** and `code`.\n";

        $this->mediumMarkdown = str_repeat(self::markdownBlock(), 8);
        $this->largeMarkdown = str_repeat(self::markdownBlock(), 360);
    }

    public function provideParsers(): iterable
    {
        yield self::PARSER_MDPARSER => ['parser' => self::PARSER_MDPARSER];
    }

    /**
     * @param array{parser: string} $params
     */
    #[Revs(1000)]
    #[Iterations(3)]
    #[Warmup(1)]
    #[ParamProviders('provideParsers')]
    public function benchRenderShortMarkdown(array $params): void
    {
        $this->render($params['parser'], $this->shortMarkdown);
    }

    /**
     * @param array{parser: string} $params
     */
    #[Revs(200)]
    #[Iterations(3)]
    #[Warmup(1)]
    #[ParamProviders('provideParsers')]
    public function benchRenderMediumMarkdown(array $params): void
    {
        $this->render($params['parser'], $this->mediumMarkdown);
    }

    /**
     * @param array{parser: string} $params
     */
    #[Revs(10)]
    #[Iterations(3)]
    #[Warmup(1)]
    #[ParamProviders('provideParsers')]
    public function benchRenderLargeMarkdown(array $params): void
    {
        $this->render($params['parser'], $this->largeMarkdown);
    }

    private function render(string $parser, string $markdown): void
    {
        if ($parser === self::PARSER_MDPARSER) {
            $this->markdownRenderer->render($markdown);
            return;
        }

        throw new RuntimeException("Unsupported markdown parser \"$parser\".");
    }

    private static function markdownBlock(): string
    {
        return <<<'MARKDOWN'
## Section

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.

- Item one with **bold** text
- Item two with *italic* text
- Item three with `inline code`
- [x] Completed task
- [ ] Pending task

```php
$value = ['markdown' => 'benchmark'];
echo $value['markdown'];
```

| A | B | C |
|---|---|---|
| 1 | 2 | 3 |
| 4 | 5 | 6 |

~~Deleted text~~ and https://example.com/

MARKDOWN;
    }
}
