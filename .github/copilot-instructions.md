# GitHub Copilot Instructions for YiiPress

## Project Overview

YiiPress is a high-performance static blog engine built on Yii3. It operates in two modes:
- **build** — static site generation
- **serve** — near realtime development preview

Performance is a first-class architectural concern. The project uses C extensions (MD4C, yaml) for hot paths and PHP for orchestration.

## Core Development Principles

### Code Style and Standards

- Postfix interfaces with `Interface` (e.g., `ContentProcessorInterface`)
- Stick to Yii3 and PHP best practices
- Performance should be exceptional
- Use features of PHP 8.5+ as specified in `composer.json`
- All domain objects should be immutable value objects (no hidden state mutations)
- Prefer composition over inheritance

### Architecture Guidelines

- Always align to [/docs/architecture.md](../docs/architecture.md) for architecture and principles
- Follow the four-stage pipeline: Parse → Index → Render → Write
- Dependencies point inward: output depends on render, render depends on index, index depends on parsing
- Keep markdown body as raw string until render time (late parsing strategy)
- Use generators for collections to support large sites with limited memory
- Leverage C extensions for hot paths (MD4C for markdown, yaml_parse for YAML)

### Performance Requirements

- Eliminate unnecessary dependencies
- Do less work (two-pass front matter extraction, lazy loading)
- Parallelize output using `pcntl_fork()` for independent entry rendering
- Leverage PHP runtime optimizations (OPCache, JIT, preloading)
- Build and serve modes must produce identical output

### Testing and Benchmarking

- For each piece of code, add a test using PHPUnit
- For each significant feature, add a benchmark using PHPBench
- Tests should validate behavior, not implementation details
- Benchmarks should track performance regressions

### Documentation

- Refer to [README.md](../README.md) and [/docs](../docs) directory for documentation
- If documentation could be improved or any feature is not documented yet, please update it
- For each feature, add appropriate documentation
- Keep documentation in sync with code changes

### Feature Planning

- Refer to [roadmap.md](../roadmap.md) for planned features and priorities
- When suggesting or implementing a new feature, check the roadmap first
- If the feature is listed, check its checkbox upon completion
- If suggesting a new feature not yet on the roadmap, add it to the appropriate priority section

## Development Environment

### Docker-Only Environment

- Do not care about running locally. Consider Docker-only environment
- Use `make` commands to run commands and test website
- Do not try to run docker, php, or composer commands directly
- Consider current DEV_PORT from `.env` file when testing website

### Available Make Commands

Check `Makefile` for available commands. Common commands include:
- `make test` — run PHPUnit tests
- `make benchmark` — run PHPBench benchmarks
- `make lint` — run linters
- `make fix` — fix code style issues

## Code Organization

### Directory Structure

```
src/
├── Console/         # CLI commands (build, serve, new, import, clean)
├── Content/         # Content model and parsing
│   ├── Model/       # Value objects (Entry, Collection, Author, etc.)
│   ├── Parser/      # File parsers (front matter, YAML configs)
│   ├── SiteIndex.php
│   └── SiteIndexBuilder.php
├── Processor/       # Content transformation pipeline
├── Render/          # Page generation and rendering
├── Build/           # Static site generation
├── Web/             # Serve mode (Yii3 web app)
└── Import/          # Content importers
```

### Key Abstractions

- **Value Objects** — `Entry`, `Collection`, `Author`, `Taxonomy`, `TaxonomyTerm`, `Page`, `SiteConfig`
- **Parsers** — `FrontMatterParser`, `CollectionConfigParser`, `SiteConfigParser`
- **Processors** — `ContentProcessorInterface` implementations (MarkdownProcessor, SyntaxHighlightProcessor)
- **Builders** — `SiteIndexBuilder` constructs the indexed site model
- **Generators** — `PageGenerator` produces output pages from the index
- **Writers** — `StaticWriter`, `HttpResponder` output rendered pages

## Memory and Performance Considerations

### Memory Strategy

- `FrontMatterParser` reads only front matter bytes via `fgets()` line-by-line
- Entry stores `bodyOffset` and `bodyLength` instead of body string
- The `body()` method reads from disk on demand via `fseek()`/`fread()`
- Parser methods return `Generator` instances, never arrays
- YAML config files are small and loaded fully

### Performance Hot Paths

- Front matter parsing — called once per entry
- Markdown-to-HTML — called once per entry during render
- Template rendering — called for each output page

### Parallelization

- Entry rendering can be parallelized via `pcntl_fork()`
- Each worker processes entries[i] where i % N == workerIndex
- No shared memory or synchronization required
- Parent process handles collection pages, feeds, sitemap serially

## File Naming and Conventions

### PHP Files

- Classes use PascalCase and match filename
- Interfaces postfixed with `Interface`
- One class per file
- Use strict types: `declare(strict_types=1);`

### Test Files

- Place in `tests/` directory matching `src/` structure
- Suffix with `Test.php` (e.g., `EntryTest.php`)
- Extend `PHPUnit\Framework\TestCase`

### Benchmark Files

- Place in `benchmarks/` directory
- Suffix with `Bench.php` (e.g., `ParseBench.php`)
- Use PHPBench annotations

## Common Patterns

### Creating Value Objects

```php
final readonly class Entry
{
    public function __construct(
        public string $title,
        public string $slug,
        // ... other properties
    ) {}
}
```

### Using Generators for Large Collections

```php
public function parseEntries(string $path): \Generator
{
    foreach ($files as $file) {
        yield $this->parseEntry($file);
    }
}
```

### Content Processors

```php
final class MyProcessor implements ContentProcessorInterface
{
    public function process(string $content, Entry $entry): string
    {
        // Transform content
        return $transformedContent;
    }
}
```

## Dependencies

### Required PHP Extensions

- `ext-ffi` — for Rust syntax highlighter via FFI
- `ext-md4c` — for markdown parsing (C library)
- `ext-yaml` — for YAML parsing (C library)
- `ext-pcntl` — for parallel rendering
- `ext-mbstring`, `ext-filter`, `ext-xmlwriter` — standard extensions

### Key Dependencies

- Yii3 — DI container, console, HTTP runner, router, view renderer
- Symfony Console — for CLI commands
- MD4C — C markdown parser (via ext-md4c)
- libyaml — C YAML parser (via ext-yaml)

## Troubleshooting

### Common Issues

1. **Memory issues with large sites** — ensure generators are used, not arrays
2. **Slow builds** — check that C extensions (md4c, yaml) are enabled
3. **Parsing errors** — validate YAML front matter syntax
4. **Template not found** — check theme directory and template resolver configuration

### Debug Tips

- Enable verbose logging in development
- Use `--dry-run` flag for build command to preview without writing files
- Check `runtime/` directory for cache and logs
- Use benchmarks to identify performance bottlenecks

## Security Considerations

- Never commit secrets or API keys
- Validate and sanitize all user input (front matter, config files)
- Use parameterized queries if database features are added
- Keep dependencies up to date

## Additional Resources

- [Architecture Documentation](../docs/architecture.md) — detailed system design
- [Quickstart Guide](../docs/quickstart.md) — getting started tutorial
- [Configuration Reference](../docs/config.md) — all config options
- [Content Guide](../docs/content.md) — front matter and content structure
- [Plugin Development](../docs/plugins.md) — plugin API and lifecycle
- [Template Guide](../docs/template.md) — template variables and helpers
- [Roadmap](../roadmap.md) — planned features and priorities
