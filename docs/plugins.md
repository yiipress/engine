# Content processors

Content processors transform entry content through a pipeline. Each processor receives the output of the previous one.

## Processor interface

A processor implements `App\Processor\ContentProcessorInterface`:

```php
interface ContentProcessorInterface
{
    public function process(string $content, Entry $entry): string;
}
```

## Content processor pipeline

`ContentProcessorPipeline` chains processors in order:

```
markdown → MarkdownProcessor → SyntaxHighlightProcessor → ... → final HTML
```

Two separate pipelines are configured via Yii3 DI container in `config/common/di/content-pipeline.php`:

- **contentPipeline** — used by `EntryRenderer`: `MarkdownProcessor` → `SyntaxHighlightProcessor`
- **feedPipeline** — used by `FeedGenerator`: `MarkdownProcessor` only (no syntax highlighting in feeds)

## Built-in processors

### MarkdownProcessor

Converts markdown to HTML using md4c. Accepts `MarkdownConfig` via constructor for feature toggles (tables, strikethrough, tasklists, etc.).

### SyntaxHighlightProcessor

Highlights code blocks server-side during build. No client-side JavaScript is needed.

Use standard fenced code blocks with a language identifier:

````markdown
```php
echo "Hello, world!";
```
````

The highlighter is a Rust library (`src/Highlighter/`) built with [syntect](https://github.com/trishume/syntect)
and [rayon](https://github.com/rayon-rs/rayon), called from PHP via FFI. It processes all
`<pre><code class="language-xxx">` blocks in the rendered HTML, replacing them with
inline-styled highlighted output.

Rayon parallelizes highlighting across code blocks within a single page, which helps
when a page contains many code blocks (e.g., documentation pages).

The library is compiled during Docker image build (multistage build) and installed as
`/usr/local/lib/libyiipress_highlighter.so`. No additional setup is needed.

Supported languages include all syntect defaults (PHP, JavaScript, Python, Rust, YAML,
Bash, SQL, HTML, CSS, and many more). Code blocks with an unrecognized language are
highlighted as plain text.

## Writing a custom processor

Create a class implementing `ContentProcessorInterface`. For example, a shortcode processor:

```php
final class ShortcodeProcessor implements ContentProcessorInterface
{
    public function process(string $content, Entry $entry): string
    {
        return preg_replace(
            '/\[youtube id="([^"]+)"\/\]/',
            '<div class="video"><iframe src="https://www.youtube.com/embed/$1"></iframe></div>',
            $content,
        );
    }
}
```

To register it, add it to `config/common/di/content-pipeline.php`. Place it before `MarkdownProcessor` since it operates on markdown:

```php
return [
    ContentProcessorPipeline::class => [
        '__construct()' => [
            new ShortcodeProcessor(),
            new MarkdownProcessor(),
            new SyntaxHighlightProcessor(),
        ],
    ],
];
```

Processor order matters — each processor receives the output of the previous one.

