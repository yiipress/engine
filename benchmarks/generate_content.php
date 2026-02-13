<?php

declare(strict_types=1);

$targetDir = $argv[1] ?? __DIR__ . '/data/content';
$entryCount = (int) ($argv[2] ?? 10_000);
$authorCount = 20;
$collectionCount = 3;

echo "Generating $entryCount entries in $targetDir...\n";

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

$body = <<<'MD'

## Introduction

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore
et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.

### Details

Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.
Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.

- Item one with **bold** text
- Item two with *italic* text
- Item three with `code` text

```php
$example = new Example();
$example->run();
```

| Column A | Column B | Column C |
|----------|----------|----------|
| Value 1  | Value 2  | Value 3  |
| Value 4  | Value 5  | Value 6  |

> A blockquote with some wisdom about static site generation.

Final paragraph with a [link](https://example.com) and some concluding thoughts.
MD;

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
            'title' => "Entry $globalIndex: Benchmark Post",
            'tags' => $entryTags,
            'categories' => [$entryCategory],
            'authors' => [$author],
            'summary' => "Summary for benchmark entry $globalIndex.",
        ], YAML_UTF8_ENCODING, YAML_LN_BREAK);

        $frontMatter = trim($frontMatter, ".\n");

        file_put_contents(
            $collectionDir . "/$date-$slug.md",
            "---\n$frontMatter\n---\n$body\n",
        );
    }
}

echo "Generated $entryCount entries across $collectionCount collections.\n";
echo "Authors: $authorCount\n";
echo "Tags: " . count($tags) . "\n";
echo "Categories: " . count($categories) . "\n";
