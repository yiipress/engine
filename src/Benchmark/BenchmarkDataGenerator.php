<?php

declare(strict_types=1);

namespace App\Benchmark;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

use function ceil;
use function count;
use function date;
use function is_dir;
use function mkdir;
use function preg_replace;
use function rtrim;
use function strtotime;
use function unlink;
use function yaml_emit;

final class BenchmarkDataGenerator
{
    public static function generateSmallDataset(string $targetDir, int $entryCount = 10_000): void
    {
        self::prepareTargetDir($targetDir);

        $authorCount = 20;
        $collectionCount = 3;

        self::writeSiteConfig($targetDir, $collectionCount);

        $authorSlugs = self::generateAuthors($targetDir, $authorCount);
        $tags = self::generateTags();
        $categories = self::generateCategories();
        $entriesPerCollection = (int) ceil($entryCount / $collectionCount);

        for ($collectionIndex = 0; $collectionIndex < $collectionCount; $collectionIndex++) {
            $collectionName = "collection-$collectionIndex";
            $collectionDir = $targetDir . '/' . $collectionName;
            mkdir($collectionDir, 0o755, true);

            self::writeCollectionConfig($collectionDir, $collectionIndex, $collectionName);

            for ($entryIndex = 0; $entryIndex < $entriesPerCollection; $entryIndex++) {
                $globalIndex = $collectionIndex * $entriesPerCollection + $entryIndex;
                if ($globalIndex >= $entryCount) {
                    break;
                }

                $date = date('Y-m-d', strtotime("2024-01-01 +$globalIndex days"));
                $slug = "entry-$globalIndex";
                $author = $authorSlugs[$globalIndex % $authorCount];
                $entryTags = [$tags[$globalIndex % count($tags)], $tags[($globalIndex + 7) % count($tags)]];
                $entryCategory = $categories[$globalIndex % count($categories)];

                $frontMatter = self::emitFrontMatter([
                    'title' => "Entry $globalIndex: Benchmark Post",
                    'tags' => $entryTags,
                    'categories' => [$entryCategory],
                    'authors' => [$author],
                    'summary' => "Summary for benchmark entry $globalIndex.",
                ]);

                $body = self::generateSmallBody($globalIndex, $collectionName);

                file_put_contents(
                    $collectionDir . "/$date-$slug.md",
                    "---\n$frontMatter\n---\n$body\n",
                );
            }
        }
    }

    public static function generateRealisticDataset(string $targetDir, int $entryCount = 1_000): void
    {
        self::prepareTargetDir($targetDir);

        $authorCount = 20;
        $collectionCount = 3;

        self::writeSiteConfig($targetDir, $collectionCount);

        $authorSlugs = self::generateAuthors($targetDir, $authorCount);
        $tags = self::generateTags();
        $categories = self::generateCategories();
        $entriesPerCollection = (int) ceil($entryCount / $collectionCount);

        for ($collectionIndex = 0; $collectionIndex < $collectionCount; $collectionIndex++) {
            $collectionName = "collection-$collectionIndex";
            $collectionDir = $targetDir . '/' . $collectionName;
            mkdir($collectionDir, 0o755, true);

            self::writeCollectionConfig($collectionDir, $collectionIndex, $collectionName);

            for ($entryIndex = 0; $entryIndex < $entriesPerCollection; $entryIndex++) {
                $globalIndex = $collectionIndex * $entriesPerCollection + $entryIndex;
                if ($globalIndex >= $entryCount) {
                    break;
                }

                $date = date('Y-m-d', strtotime("2024-01-01 +$globalIndex days"));
                $slug = "entry-$globalIndex";
                $author = $authorSlugs[$globalIndex % $authorCount];
                $entryTags = [$tags[$globalIndex % count($tags)], $tags[($globalIndex + 7) % count($tags)]];
                $entryCategory = $categories[$globalIndex % count($categories)];

                $frontMatter = self::emitFrontMatter([
                    'title' => "Entry $globalIndex: Comprehensive Benchmark Post",
                    'tags' => $entryTags,
                    'categories' => [$entryCategory],
                    'authors' => [$author],
                    'summary' => "Summary for realistic benchmark entry $globalIndex.",
                ]);

                $body = self::generateRealisticBody($globalIndex, $entryCount, $entriesPerCollection, $collectionCount);

                file_put_contents(
                    $collectionDir . "/$date-$slug.md",
                    "---\n$frontMatter\n---\n$body\n",
                );
            }
        }
    }

    private static function prepareTargetDir(string $targetDir): void
    {
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

        if (!is_dir($targetDir) && !mkdir($targetDir, 0o755, true) && !is_dir($targetDir)) {
            throw new RuntimeException("Failed to create benchmark directory: $targetDir");
        }
    }

    private static function writeSiteConfig(string $targetDir, int $collectionCount): void
    {
        file_put_contents($targetDir . '/config.yaml', <<<'YAML'
title: "Benchmark Site"
description: "A site with many entries for benchmarking"
base_url: "https://example.com"
language: "en"
charset: "utf-8"
YAML);

        $menus = ['main' => []];
        for ($collectionIndex = 0; $collectionIndex < $collectionCount; $collectionIndex++) {
            $menus['main'][] = ['title' => "Collection $collectionIndex", 'url' => "/collection-$collectionIndex/"];
        }

        file_put_contents($targetDir . '/navigation.yaml', yaml_emit(['menus' => $menus]));
    }

    /**
     * @return list<string>
     */
    private static function generateAuthors(string $targetDir, int $authorCount): array
    {
        $authorsDir = $targetDir . '/authors';
        mkdir($authorsDir, 0o755, true);

        $authorSlugs = [];
        for ($authorIndex = 0; $authorIndex < $authorCount; $authorIndex++) {
            $slug = "author-$authorIndex";
            $authorSlugs[] = $slug;

            file_put_contents($authorsDir . "/$slug.md", <<<YAML
---
title: "Author $authorIndex"
email: "author$authorIndex@example.com"
---

Bio for author $authorIndex. This is a short biography paragraph.
YAML);
        }

        return $authorSlugs;
    }

    /**
     * @return list<string>
     */
    private static function generateTags(): array
    {
        $tags = [];
        for ($tagIndex = 0; $tagIndex < 50; $tagIndex++) {
            $tags[] = "tag-$tagIndex";
        }

        return $tags;
    }

    /**
     * @return list<string>
     */
    private static function generateCategories(): array
    {
        $categories = [];
        for ($categoryIndex = 0; $categoryIndex < 10; $categoryIndex++) {
            $categories[] = "category-$categoryIndex";
        }

        return $categories;
    }

    private static function writeCollectionConfig(string $collectionDir, int $collectionIndex, string $collectionName): void
    {
        file_put_contents($collectionDir . '/_collection.yaml', <<<YAML
title: "Collection $collectionIndex"
description: "Benchmark collection $collectionIndex"
permalink: "/$collectionName/:slug/"
sort_by: "date"
sort_order: "desc"
entries_per_page: 20
feed: true
listing: true
YAML);
    }

    /**
     * @param array<string, mixed> $frontMatter
     */
    private static function emitFrontMatter(array $frontMatter): string
    {
        $yaml = yaml_emit($frontMatter, YAML_UTF8_ENCODING, YAML_LN_BREAK);
        $yaml = preg_replace('/^---\n|\.\.\.\n?$/', '', $yaml) ?? $yaml;

        return rtrim($yaml, "\n");
    }

    private static function generateSmallBody(int $entryIndex, string $collectionName): string
    {
        $language = match ($entryIndex % 4) {
            0 => 'php',
            1 => 'yaml',
            2 => 'javascript',
            default => 'json',
        };
        $hasCodeBlock = $entryIndex % 10 < 3;

        $codeBlock = match ($language) {
            'php' => <<<CODE
```php
\$article = new ArticleRenderer('entry-$entryIndex');
\$article->setCollection('$collectionName');
\$article->render();
```
CODE,
            'yaml' => <<<CODE
```yaml
entry: entry-$entryIndex
collection: $collectionName
draft: false
```
CODE,
            'javascript' => <<<CODE
```javascript
const entryId = 'entry-$entryIndex';
renderEntry(entryId, '$collectionName');
```
CODE,
            default => <<<CODE
```json
{"entry":"entry-$entryIndex","collection":"$collectionName","published":true}
```
CODE,
        };

        $detailsSection = $hasCodeBlock ? $codeBlock : <<<TEXT
This entry focuses on prose content without a source listing. It still references
entry-$entryIndex in collection $collectionName and describes the same feature set
using regular paragraphs instead of a code example.
TEXT;

        return <<<MD

## Introduction

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore
et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.

### Details

Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.
Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.

- Item one with **bold** text
- Item two with *italic* text
- Item three with `code` text

$detailsSection

| Column A | Column B | Column C |
|----------|----------|----------|
| Value $entryIndex  | Value $collectionName  | Value $language  |
| Value 4  | Value 5  | Value 6  |

> A blockquote with some wisdom about static site generation.

Final paragraph with a [link](https://example.com/entries/$entryIndex) and some concluding thoughts.
MD;
    }

    private static function resolveCollectionPathForEntry(
        int $entryIndex,
        int $entriesPerCollection,
        int $collectionCount,
    ): string {
        $collectionIndex = min(intdiv($entryIndex, $entriesPerCollection), $collectionCount - 1);

        return "/collection-$collectionIndex/entry-$entryIndex/";
    }

    private static function generateRealisticBody(
        int $entryIndex,
        int $totalEntries,
        int $entriesPerCollection,
        int $collectionCount,
    ): string {
        $sections = [];
        $hasCodeExamples = $entryIndex % 10 < 3;

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
        for ($linkIndex = 0; $linkIndex < 5; $linkIndex++) {
            $linked = ($entryIndex + $linkIndex * 7 + 1) % $totalEntries;
            $linkedEntries[] = $linked;
        }

        $relatedUrls = array_map(
            static fn (int $linked): string => self::resolveCollectionPathForEntry($linked, $entriesPerCollection, $collectionCount),
            $linkedEntries,
        );

        $sections[] = <<<MD
## Related Articles

Here are some related articles you might find interesting:

- [Entry $linkedEntries[0]: A Deep Dive]({$relatedUrls[0]})
- [Entry $linkedEntries[1]: Advanced Techniques]({$relatedUrls[1]})
- [Entry $linkedEntries[2]: Getting Started Guide]({$relatedUrls[2]})
- [Entry $linkedEntries[3]: Best Practices]({$relatedUrls[3]})
- [Entry $linkedEntries[4]: Performance Tips]({$relatedUrls[4]})

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
            if ($hasCodeExamples) {
                $sections[] = <<<MD
## Chapter $page: Extended Content

### Section $page.1: Background

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

### Section $page.2: Implementation Details

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
        \$cached = \$this->cache->store(\$entry->slug, \$html);

        return \$cached;
    }
}
```

### Section $page.3: Additional Considerations

Morbi in sem quis dui placerat ornare. Pellentesque odio nisi, euismod in, pharetra a, ultricies
in, diam. Sed arcu. Cras consequat. Praesent dapibus, neque id cursus faucibus, tortor neque
egestas augue, eu vulputate magna eros eu erat. Aliquam erat volutpat. Nam dui mi, tincidunt quis,
accumsan porttitor, facilisis luctus, metus.
MD;
                continue;
            }

            $sections[] = <<<MD
## Chapter $page: Extended Content

### Section $page.1: Background

Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium,
totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae
dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit.

### Section $page.2: Implementation Details

This chapter focuses on architecture rather than verbatim code. It explains how chapter $page
coordinates parsing, rendering, caching, and output writing without embedding a source listing.

### Section $page.3: Additional Considerations

Morbi in sem quis dui placerat ornare. Pellentesque odio nisi, euismod in, pharetra a, ultricies
in, diam. Sed arcu. Cras consequat. Praesent dapibus, neque id cursus faucibus, tortor neque
egestas augue, eu vulputate magna eros eu erat.
MD;
        }

        return "\n" . implode("\n\n", $sections) . "\n";
    }
}
