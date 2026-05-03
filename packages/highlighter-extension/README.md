# YiiPress Highlighter Extension

Native PHP extension for server-side syntax highlighting. It exposes the public PHP API as
`YiiPress\Highlighter`.

## Install With PIE

After publishing the package to Packagist, install it with:

```bash
pie install yiipress/highligher
```

The build requires `cargo`, `phpize`, `php-config`, a C compiler, and `make`.

## Manual Build

```bash
phpize
./configure --enable-yiipress-highlighter
make
make install
```

Then enable the extension in PHP:

```ini
extension=yiipress_highlighter
```

## PHP API

```php
use YiiPress\Highlighter;

$highlighter = new Highlighter();
$html = $highlighter->highlightHtml(
    '<pre><code class="language-php">&lt;?php echo "Hello";</code></pre>',
);
```

Pass a syntect theme name as the second argument:

```php
$html = $highlighter->highlightHtml($html, 'Solarized (dark)');
```

Highlight raw code without wrapping it in `<pre><code>` first:

```php
$html = $highlighter->highlight('echo "Hello";', 'php');
```

Use `class_exists(YiiPress\Highlighter::class)` when code needs to detect whether the
extension is loaded in the current PHP process.
