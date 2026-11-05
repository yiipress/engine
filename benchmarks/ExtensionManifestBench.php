<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use FilesystemIterator;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Manifest\ManifestParser;
use YiiPress\Extension\Manifest\ManifestReader;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
#[Revs(200)]
#[Iterations(5)]
#[Warmup(1)]
final class ExtensionManifestBench
{
    private const int ENTRY_POINTS = 50;

    private const string THEME = <<<'YAML'
        manifest: 1
        id: yiipress/wonder
        type: theme
        name: Wonder
        description: A clean blog theme.
        version: 1.0.0
        yiipress: ^0.1.13
        license: BSD-3-Clause
        authors: [{name: Jane Doe, email: jane@example.com}]
        homepage: https://example.com/wonder
        theme:
          screenshots: [screenshots/home.png]
        YAML;

    private const string PROCESSOR = <<<'YAML'
        manifest: 1
        id: example/emoji
        type: processor
        name: Emoji
        description: "Replaces :shortcodes: with emoji."
        version: 0.2.0
        yiipress: ^0.1.13
        license: MIT
        authors: [{name: Example Team}]
        processor:
          entry_points:
            - path: src/emoji.php
              stages: [before_markdown]
          examples:
            - input: examples/in.md
              output: examples/out.html
        YAML;

    private string $baseDir;
    private string $themeRoot;
    private string $processorRoot;
    private string $manyEntryPointsRoot;
    private string $manyEntryPointsYaml;
    private ManifestParser $parser;
    private ManifestReader $reader;

    public function setUp(): void
    {
        $this->manyEntryPointsYaml = self::manyEntryPointsYaml();
        $this->parser = new ManifestParser();
        $this->reader = new ManifestReader();

        $this->baseDir = sys_get_temp_dir() . '/yiipress-extension-manifest-bench-' . uniqid();

        $this->themeRoot = $this->baseDir . '/theme';
        self::write($this->themeRoot . '/extension.yaml', self::THEME);
        self::write($this->themeRoot . '/screenshots/home.png', 'png');
        mkdir($this->themeRoot . '/package', 0o755, true);

        $this->processorRoot = $this->baseDir . '/processor';
        self::write($this->processorRoot . '/extension.yaml', self::PROCESSOR);
        self::write($this->processorRoot . '/package/src/emoji.php', '<?php');
        self::write($this->processorRoot . '/examples/in.md', ':smile:');
        self::write($this->processorRoot . '/examples/out.html', '<p>😄</p>');

        $this->manyEntryPointsRoot = $this->baseDir . '/many';
        self::write($this->manyEntryPointsRoot . '/extension.yaml', $this->manyEntryPointsYaml);
        for ($i = 1; $i <= self::ENTRY_POINTS; $i++) {
            self::write($this->manyEntryPointsRoot . '/package/src/step' . $i . '.php', '<?php');
        }
    }

    public function tearDown(): void
    {
        if (!is_dir($this->baseDir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->baseDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->baseDir);
    }

    public function benchParseTheme(): void
    {
        $this->parser->parse(self::THEME);
    }

    public function benchParseProcessor(): void
    {
        $this->parser->parse(self::PROCESSOR);
    }

    public function benchParseManyEntryPoints(): void
    {
        $this->parser->parse($this->manyEntryPointsYaml);
    }

    public function benchReadTheme(): void
    {
        $this->reader->read($this->themeRoot, ExtensionType::Theme);
    }

    public function benchReadProcessor(): void
    {
        $this->reader->read($this->processorRoot, ExtensionType::Processor);
    }

    public function benchReadManyEntryPoints(): void
    {
        $this->reader->read($this->manyEntryPointsRoot, ExtensionType::Processor);
    }

    private static function manyEntryPointsYaml(): string
    {
        $yaml = <<<'YAML'
            manifest: 1
            id: example/pipeline
            type: processor
            name: Pipeline
            description: Runs many small steps.
            version: 1.0.0
            yiipress: ^0.1.13
            license: MIT
            authors: [{name: Example Team}]
            processor:
              entry_points:

            YAML;
        for ($i = 1; $i <= self::ENTRY_POINTS; $i++) {
            $yaml .= "    - path: src/step{$i}.php\n      stages: [before_markdown, after_markdown]\n";
        }

        return $yaml;
    }

    private static function write(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }
        file_put_contents($path, $content);
    }
}
