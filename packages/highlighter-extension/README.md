# YiiPress Highlighter Extension

Native PHP extension and PHP wrapper for server-side syntax highlighting. The public PHP API is
`YiiPress\Highlighter`; the raw extension function is an implementation detail.

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
$html = $highlighter->highlight(
    '<pre><code class="language-php">&lt;?php echo "Hello";</code></pre>',
);
```

Pass a syntect theme name as the second argument:

```php
$html = $highlighter->highlight($html, 'Solarized (dark)');
```

`YiiPress\Highlighter::isAvailable()` returns whether `ext-yiipress_highlighter`
is loaded in the current PHP process.
