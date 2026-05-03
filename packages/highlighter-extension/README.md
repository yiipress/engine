# YiiPress Highlighter Extension

Native PHP extension for YiiPress syntax highlighting. It exposes `yiipress_highlight_html()` and links the Rust `syntect` highlighter statically into the PHP module.

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
