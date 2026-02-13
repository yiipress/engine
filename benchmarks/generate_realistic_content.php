<?php

declare(strict_types=1);

$targetDir = $argv[1] ?? __DIR__ . '/data/realistic-content';
$entryCount = (int) ($argv[2] ?? 1_000);
$authorCount = 20;
$collectionCount = 3;

echo "Generating $entryCount realistic entries in $targetDir...\n";

if (is_dir($targetDir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($targetDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
        /** @var SplFileInfo $item */
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
}

mkdir($targetDir, 0o755, true);

file_put_contents($targetDir . '/config.yaml', <<<'YAML'
title: "Benchmark Site"
description: "A site with many entries for benchmarking"
base_url: "https://example.com"
language: "en"
charset: "utf-8"
YAML);

$menus = ['main' => []];
for ($c = 0; $c < $collectionCount; $c++) {
    $menus['main'][] = ['title' => "Collection $c", 'url' => "/collection-$c/"];
}
file_put_contents($targetDir . '/navigation.yaml', yaml_emit(['menus' => $menus]));

$authorsDir = $targetDir . '/authors';
mkdir($authorsDir, 0o755, true);
$authorSlugs = [];
for ($a = 0; $a < $authorCount; $a++) {
    $slug = "author-$a";
    $authorSlugs[] = $slug;
    file_put_contents($authorsDir . "/$slug.md", <<<YAML
---
title: "Author $a"
email: "author$a@example.com"
---

Bio for author $a. This is a short biography paragraph.
YAML);
}

$tags = [];
for ($t = 0; $t < 50; $t++) {
    $tags[] = "tag-$t";
}

$categories = [];
for ($cat = 0; $cat < 10; $cat++) {
    $categories[] = "category-$cat";
}

function generateRealisticBody(int $entryIndex, int $totalEntries): string
{
    $sections = [];

    $sections[] = <<<'MD'
## Introduction

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore
et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut
aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse
cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in
culpa qui officia deserunt mollit anim id est laborum.

Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas.
Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero
sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.
Quisque sit amet est et sapien ullamcorper pharetra. Vestibulum erat wisi, condimentum sed,
commodo vitae, ornare sit amet, wisi.
MD;

    $linkedEntries = [];
    for ($i = 0; $i < 5; $i++) {
        $linked = ($entryIndex + $i * 7 + 1) % $totalEntries;
        $linkedEntries[] = $linked;
    }

    $sections[] = <<<MD
## Related Articles

Here are some related articles you might find interesting:

- [Entry $linkedEntries[0]: A Deep Dive](/collection-0/entry-$linkedEntries[0]/)
- [Entry $linkedEntries[1]: Advanced Techniques](/collection-1/entry-$linkedEntries[1]/)
- [Entry $linkedEntries[2]: Getting Started Guide](/collection-2/entry-$linkedEntries[2]/)
- [Entry $linkedEntries[3]: Best Practices](/collection-0/entry-$linkedEntries[3]/)
- [Entry $linkedEntries[4]: Performance Tips](/collection-1/entry-$linkedEntries[4]/)

For more context, see also the [official documentation](https://example.com/docs) and the
[API reference](https://example.com/api). You can also check the [FAQ](https://example.com/faq)
and the [community forum](https://example.com/forum).
MD;

    $sections[] = <<<'MD'
## Visual Content

Here are some diagrams and screenshots illustrating the concepts:

![Architecture Overview](https://example.com/images/architecture-overview.png)

The architecture diagram above shows the main components. Below is a more detailed view:

![Component Detail](https://example.com/images/component-detail.jpg)

And here's the deployment pipeline:

![Deployment Pipeline](https://example.com/images/deployment-pipeline.svg)

![Performance Graph](https://example.com/images/perf-graph.png)

![Screenshot of Dashboard](https://example.com/images/dashboard-screenshot.png)
MD;

    $sections[] = <<<'MD'
## Styled Text Examples

This section demonstrates **various text styles** used throughout the article. We use *italic text*
for emphasis, **bold text** for strong emphasis, and ***bold italic*** for maximum emphasis.
There's also `inline code` for technical terms, and ~~strikethrough~~ for corrections.

Here's a paragraph with mixed styling: The `ContentParser` class uses **lazy loading** to defer
*markdown body reading* until render time. This means that `Entry::body()` performs a ***deferred
file read*** using `fseek()` and `fread()`, which is ~~slower~~ actually faster than loading
everything into memory upfront.

### Nested Formatting

- **Bold list item** with *nested italic* and `code`
- *Italic list item* with **nested bold** and ~~strikethrough~~
- `Code list item` with **bold** and *italic* siblings
- ***Bold italic item*** with `code` and [a link](https://example.com)

> **Important:** This blockquote contains **bold text**, *italic text*, and `code`.
> It spans multiple lines and demonstrates how formatting works inside blockquotes.
>
> > Nested blockquote with **more formatting** and a [link](https://example.com/nested).
MD;

    $sections[] = <<<'MD'
## Data Tables

### Performance Comparison

| Engine | Parse Time | Render Time | Total Build | Memory Peak | Entries/sec |
|--------|-----------|-------------|-------------|-------------|-------------|
| YiiPress | 96ms | 196ms | 1.54s | 30MB | 6,494 |
| Hugo | 120ms | 250ms | 1.80s | 45MB | 5,556 |
| Jekyll | 2,500ms | 8,000ms | 12.5s | 512MB | 800 |
| Eleventy | 800ms | 1,200ms | 3.5s | 128MB | 2,857 |
| Gatsby | 3,000ms | 5,000ms | 15.0s | 1024MB | 667 |

### Feature Matrix

| Feature | YiiPress | Hugo | Jekyll | Eleventy |
|---------|----------|------|--------|----------|
| Markdown | ✓ | ✓ | ✓ | ✓ |
| YAML Front Matter | ✓ | ✓ | ✓ | ✓ |
| Taxonomies | ✓ | ✓ | ✓ | ✓ |
| Parallel Build | ✓ | ✓ | ✗ | ✗ |
| Incremental Build | ✓ | ✓ | ✓ | ✓ |
| Live Reload | ✓ | ✓ | ✓ | ✓ |
| Plugin System | ✓ | ✗ | ✓ | ✓ |
| Custom Templates | ✓ | ✓ | ✓ | ✓ |

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `workers` | int | 1 | Number of parallel build workers |
| `content-dir` | string | `content` | Path to content directory |
| `output-dir` | string | `output` | Path to output directory |
| `no-cache` | bool | false | Disable build cache |
| `base-url` | string | `/` | Base URL for the site |
| `draft` | bool | false | Include draft entries |
MD;

    for ($page = 1; $page <= 6; $page++) {
        $sections[] = <<<MD
## Chapter $page: Extended Content

### Section {$page}.1: Background

Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium,
totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae
dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit,
sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam
est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius
modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem.

Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut
aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit
esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur?

At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum
deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non
provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum
fuga. Et harum quidem rerum facilis est et expedita distinctio.

### Section {$page}.2: Implementation Details

Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod
maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus. Temporibus
autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates
repudiandae sint et molestiae non recusandae. Itaque earum rerum hic tenetur a sapiente delectus,
ut aut reiciendis voluptatibus maiores alias consequatur aut perferendis doloribus asperiores
repellat.

Here's a code example for this chapter:

```php
final class Chapter{$page}Handler
{
    public function __construct(
        private readonly ContentParser \$parser,
        private readonly MarkdownRenderer \$renderer,
        private readonly BuildCache \$cache,
    ) {}

    public function handle(Entry \$entry): string
    {
        \$body = \$entry->body();
        \$html = \$this->renderer->render(\$body);
        \$this->cache->set(\$entry->sourceFilePath(), \$html);
        return \$html;
    }
}
```

The implementation above demonstrates the **render-and-cache** pattern. Each `Entry` is processed
independently, which enables *parallel execution* across multiple `pcntl_fork()` workers.

| Metric | Chapter $page | Previous | Delta |
|--------|-------------|----------|-------|
| Lines of code | {$page}42 | {$page}38 | +4 |
| Test coverage | 9{$page}% | 8{$page}% | +10% |
| Build time impact | +{$page}ms | — | — |
| Memory overhead | +{$page}KB | — | — |

### Section {$page}.3: Analysis

Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla
pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt
mollit anim id est laborum. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do
eiusmod tempor incididunt ut labore et dolore magna aliqua.

1. First, we initialize the **parser** with the content directory
2. Then, we iterate over all *collections* and their entries
3. For each entry, we check the `BuildCache` for a cached version
4. If not cached, we render the markdown and apply the template
5. Finally, we write the output to the `output/` directory

![Chapter $page Diagram](https://example.com/images/chapter-$page-diagram.png)

See also [Entry {$linkedEntries[0]}](/collection-0/entry-{$linkedEntries[0]}/) for more details
on this topic, and [the architecture docs](https://example.com/docs/architecture) for the
overall design.
MD;
    }

    $sections[] = <<<'MD'
## Conclusion

In summary, this article covered the following key points:

1. **Architecture** — the overall design and component structure
2. **Performance** — benchmarking results and optimization strategies
3. **Implementation** — code examples and patterns used
4. **Comparison** — how this approach compares to alternatives

The results demonstrate that a well-optimized static site generator can process thousands of
entries per second while maintaining low memory usage. The combination of **lazy loading**,
**parallel processing**, and **file-based caching** provides a robust foundation for scaling
to very large sites.

For questions or feedback, please visit the [community forum](https://example.com/forum) or
open an issue on [GitHub](https://github.com/example/yiipress).

---

*Last updated: 2024-03-15. This article is part of the [YiiPress documentation](https://example.com/docs) series.*
MD;

    return implode("\n\n", $sections);
}

$entriesPerCollection = (int) ceil($entryCount / $collectionCount);

for ($c = 0; $c < $collectionCount; $c++) {
    $collectionName = "collection-$c";
    $collectionDir = $targetDir . '/' . $collectionName;
    mkdir($collectionDir, 0o755, true);

    file_put_contents($collectionDir . '/_collection.yaml', <<<YAML
title: "Collection $c"
description: "Benchmark collection $c"
permalink: "/$collectionName/:slug/"
sort_by: "date"
sort_order: "desc"
entries_per_page: 20
feed: true
listing: true
YAML);

    for ($e = 0; $e < $entriesPerCollection; $e++) {
        $globalIndex = $c * $entriesPerCollection + $e;
        if ($globalIndex >= $entryCount) {
            break;
        }

        $date = date('Y-m-d', strtotime("2024-01-01 +{$globalIndex} days"));
        $slug = "entry-$globalIndex";
        $author = $authorSlugs[$globalIndex % $authorCount];
        $entryTags = [$tags[$globalIndex % count($tags)], $tags[($globalIndex + 7) % count($tags)]];
        $entryCategory = $categories[$globalIndex % count($categories)];

        $frontMatter = yaml_emit([
            'title' => "Entry $globalIndex: Comprehensive Benchmark Post",
            'tags' => $entryTags,
            'categories' => [$entryCategory],
            'authors' => [$author],
            'summary' => "A comprehensive benchmark entry with links, images, tables, and 10 pages of styled text.",
        ], YAML_UTF8_ENCODING, YAML_LN_BREAK);

        $frontMatter = trim($frontMatter, ".\n");
        $body = generateRealisticBody($globalIndex, $entryCount);

        file_put_contents(
            $collectionDir . "/$date-$slug.md",
            "---\n$frontMatter\n---\n\n$body\n",
        );
    }
}

$sampleFile = $targetDir . '/collection-0/' . date('Y-m-d', strtotime('2024-01-01')) . '-entry-0.md';
$sampleSize = filesize($sampleFile);
echo "Generated $entryCount realistic entries across $collectionCount collections.\n";
echo "Sample entry size: " . number_format($sampleSize) . " bytes (~" . round($sampleSize / 1024) . " KB)\n";
echo "Authors: $authorCount\n";
echo "Tags: " . count($tags) . "\n";
echo "Categories: " . count($categories) . "\n";
