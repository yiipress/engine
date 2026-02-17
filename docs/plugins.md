# Plugins

Plugins are stored in the `plugins` directory.

## Syntax highlighting

Code blocks with a language annotation are highlighted server-side during build.
No client-side JavaScript is needed.

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

## Custom parsers

Custom parsers are used to parse content from source files and are executed before primary markdown parsing.

For example, you can create a custom parser for shortcode such as `[youtube id="..."/]`
that will produce an HTML snippet embedding a YouTube video.

