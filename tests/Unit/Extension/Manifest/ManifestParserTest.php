<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Manifest;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Manifest\InvalidManifestException;
use YiiPress\Extension\Manifest\ManifestError;
use YiiPress\Extension\Manifest\ManifestParser;
use YiiPress\Extension\Manifest\ProcessorStage;
use YiiPress\Extension\Manifest\ThemeFallback;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertNotNull;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class ManifestParserTest extends TestCase
{
    private const string THEME = <<<'YAML'
        manifest: 1
        id: yiipress/wonder
        type: theme
        name: Wonder
        description: A clean blog theme with dark mode.
        version: 1.0.0
        yiipress: ^0.1.13
        license: BSD-3-Clause
        authors:
          - name: Jane Doe
            url: https://example.com
        theme:
          fallback: minimal
          screenshots: [screenshots/home.png]
        YAML;

    private const string PROCESSOR = <<<'YAML'
        manifest: 1
        id: example/emoji
        type: processor
        name: Emoji
        description: "Replaces :shortcodes: with emoji."
        version: 0.2.0
        yiipress: ">=0.1.13 <0.3"
        license: MIT
        authors: [{name: Example Team}]
        processor:
          entry_points:
            - path: emoji.php
              stages: [before_markdown]
        YAML;

    public function testParsesTheme(): void
    {
        $manifest = new ManifestParser()->parse(self::THEME);

        assertSame(1, $manifest->manifest);
        assertSame('yiipress/wonder', (string) $manifest->id);
        assertSame(ExtensionType::Theme, $manifest->type);
        assertSame('Wonder', $manifest->name);
        assertSame('A clean blog theme with dark mode.', $manifest->description);
        assertSame('1.0.0', $manifest->version);
        assertSame('^0.1.13', $manifest->yiipress);
        assertSame('BSD-3-Clause', $manifest->license);
        assertCount(1, $manifest->authors);
        assertSame('Jane Doe', $manifest->authors[0]->name);
        assertNull($manifest->authors[0]->email);
        assertSame('https://example.com', $manifest->authors[0]->url);
        assertNull($manifest->homepage);
        assertSame([], $manifest->platforms->os);
        assertSame([], $manifest->platforms->arch);
        assertNull($manifest->processor());

        $theme = $manifest->theme();
        assertNotNull($theme);
        assertSame(ThemeFallback::Minimal, $theme->fallback);
        assertSame(['screenshots/home.png'], $theme->screenshots);
        assertNull($theme->intendedUse);
        assertNull($theme->demo);
    }

    public function testParsesProcessor(): void
    {
        $manifest = new ManifestParser()->parse(self::PROCESSOR);

        assertSame(ExtensionType::Processor, $manifest->type);
        assertSame('>=0.1.13 <0.3', $manifest->yiipress);
        assertNull($manifest->theme());

        $processor = $manifest->processor();
        assertNotNull($processor);
        assertCount(1, $processor->entryPoints);
        assertSame('emoji.php', $processor->entryPoints[0]->path);
        assertSame([ProcessorStage::BeforeMarkdown], $processor->entryPoints[0]->stages);
        assertSame([], $processor->examples);
    }

    public function testThemeSectionMayBeOmitted(): void
    {
        $theme = new ManifestParser()->parse(self::without(self::THEME, 'theme'))->theme();

        assertNotNull($theme);
        assertSame(ThemeFallback::Minimal, $theme->fallback);
        assertSame([], $theme->screenshots);
    }

    public function testParsesAllOptionalFields(): void
    {
        $yaml = self::PROCESSOR . <<<'YAML'

              examples:
                - input: examples/in.md
                  output: examples/out.html
            homepage: https://example.com/emoji
            platforms:
              os: [linux, macos]
              arch: [amd64]
            YAML;
        $yaml = str_replace('[{name: Example Team}]', '[{name: Example Team, email: team@example.com}]', $yaml);
        $yaml = str_replace('[before_markdown]', '[before_markdown, after_markdown]', $yaml);

        $manifest = new ManifestParser()->parse($yaml);

        assertSame('https://example.com/emoji', $manifest->homepage);
        assertSame(['linux', 'macos'], $manifest->platforms->os);
        assertSame(['amd64'], $manifest->platforms->arch);
        assertSame('team@example.com', $manifest->authors[0]->email);
        $processor = $manifest->processor();
        assertNotNull($processor);
        assertSame([ProcessorStage::BeforeMarkdown, ProcessorStage::AfterMarkdown], $processor->entryPoints[0]->stages);
        assertSame('examples/in.md', $processor->examples[0]->input);
        assertSame('examples/out.html', $processor->examples[0]->output);
    }

    public function testParsesThemeOptionalFields(): void
    {
        $yaml = str_replace(
            '  fallback: minimal',
            "  fallback: none\n  intended_use: Personal blogs\n  demo: https://demo.example.com",
            self::THEME,
        );

        $theme = new ManifestParser()->parse($yaml)->theme();

        assertNotNull($theme);
        assertSame(ThemeFallback::None, $theme->fallback);
        assertSame('Personal blogs', $theme->intendedUse);
        assertSame('https://demo.example.com', $theme->demo);
    }

    public function testCollectsEveryErrorAtOnce(): void
    {
        $yaml = str_replace(['name: Wonder', 'version: 1.0.0'], ['name: ""', 'version: v1'], self::THEME);

        $errors = self::errorsOf($yaml);

        assertSame(['field_invalid', 'version_invalid'], array_map(static fn(ManifestError $e) => $e->code, $errors));
    }

    public function testExceptionMessageListsEveryError(): void
    {
        $yaml = str_replace(['name: Wonder', 'version: 1.0.0'], ['name: ""', 'version: v1'], self::THEME);

        try {
            new ManifestParser()->parse($yaml);
            self::fail('Expected an invalid manifest.');
        } catch (InvalidManifestException $exception) {
            assertStringContainsString('extension.yaml has 2 errors:', $exception->getMessage());
            assertStringContainsString('- name: must be 1 to 100 characters long.', $exception->getMessage());
            assertStringContainsString('- version: "v1" is not a SemVer version', $exception->getMessage());
            assertSame('Invalid extension manifest', $exception->getName());
            assertNotNull($exception->getSolution());
        }
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function invalidManifestDataProvider(): iterable
    {
        $theme = self::THEME;
        $processor = self::PROCESSOR;

        yield 'yaml syntax' => ["manifest: [1\n", 'yaml_invalid', '', 'is not valid YAML'];
        yield 'invalid utf-8' => ["name: \xFF\n", 'yaml_invalid', '', 'is not valid UTF-8'];
        yield 'several documents' => ["manifest: 1\n---\nmanifest: 1\n", 'yaml_invalid', '', 'exactly one YAML document'];
        yield 'too large' => [str_repeat('#', 65537), 'file_too_large', '', 'larger than 64 KiB'];
        yield 'too many nodes' => [self::manyNodes(), 'file_too_large', '', 'more than 10000 YAML nodes'];
        yield 'top level list' => ["- a\n- b\n", 'not_mapping', '', 'must be a mapping'];
        yield 'empty document' => ['', 'not_mapping', '', 'must be a mapping'];
        yield 'missing name' => [self::without($theme, 'name'), 'field_missing', 'name', 'is required'];
        yield 'null name' => [str_replace('name: Wonder', 'name: ~', $theme), 'field_missing', 'name', 'is required'];
        yield 'name type' => [str_replace('name: Wonder', 'name: [a]', $theme), 'field_type', 'name', 'must be a string'];
        yield 'manifest type' => [str_replace('manifest: 1', 'manifest: "1"', $theme), 'field_type', 'manifest', 'must be an integer'];
        yield 'unknown field' => [$theme . "\nentry_point: x", 'field_unknown', 'entry_point', 'unknown field'];
        yield 'unknown nested field' => [str_replace('  fallback: minimal', '  fallbak: minimal', $theme), 'field_unknown', 'theme.fallbak', 'unknown field'];
        yield 'other type section' => [$theme . "\nprocessor: {}", 'field_unknown', 'processor', 'only when type is "processor"'];
        yield 'reserved section' => [$theme . "\nimporter: {}", 'field_unknown', 'importer', 'reserved'];
        yield 'name too long' => [str_replace('name: Wonder', 'name: ' . str_repeat('w', 101), $theme), 'field_invalid', 'name', '1 to 100 characters'];
        yield 'name two lines' => [str_replace('name: Wonder', 'name: "a\nb"', $theme), 'field_invalid', 'name', 'one line'];
        yield 'description control' => [str_replace('description: A clean blog theme with dark mode.', 'description: "A\tclean"', $theme), 'field_invalid', 'description', 'control characters'];
        yield 'license characters' => [str_replace('license: BSD-3-Clause', 'license: MIT/GPL', $theme), 'field_invalid', 'license', 'license'];
        yield 'no authors' => [str_replace("authors:\n  - name: Jane Doe\n    url: https://example.com", 'authors: []', $theme), 'field_invalid', 'authors', '1 to 20 items'];
        yield 'author not mapping' => [str_replace('[{name: Example Team}]', '[Example Team]', $processor), 'not_mapping', 'authors[0]', 'must be a mapping'];
        yield 'author without name' => [str_replace('[{name: Example Team}]', '[{email: a@example.com}]', $processor), 'field_missing', 'authors[0].name', 'is required'];
        yield 'duplicate screenshot' => [str_replace('[screenshots/home.png]', '[a.png, a.png]', $theme), 'field_duplicate', 'theme.screenshots[1]', 'repeats'];
        yield 'manifest newer' => [str_replace('manifest: 1', 'manifest: 2', $theme), 'manifest_unsupported', 'manifest', 'requires a newer YiiPress'];
        yield 'manifest zero' => [str_replace('manifest: 1', 'manifest: 0', $theme), 'manifest_unsupported', 'manifest', 'not supported'];
        yield 'id pattern' => [str_replace('yiipress/wonder', 'YiiPress/Wonder', $theme), 'id_invalid', 'id', '"YiiPress/Wonder"'];
        yield 'id reserved' => [str_replace('yiipress/wonder', 'vendor/aux', $theme), 'id_invalid', 'id', 'reserved'];
        yield 'theme named minimal' => [str_replace('yiipress/wonder', 'yiipress/minimal', $theme), 'id_reserved', 'id', '"minimal"'];
        yield 'theme named local' => [str_replace('yiipress/wonder', 'acme/local', $theme), 'id_reserved', 'id', '"local"'];
        yield 'type invalid' => [str_replace('type: theme', 'type: skin', $theme), 'type_invalid', 'type', '"skin"'];
        yield 'type importer' => [str_replace('type: theme', 'type: importer', self::without($theme, 'theme')), 'type_unsupported', 'type', 'not supported yet'];
        yield 'version prefix' => [str_replace('version: 1.0.0', 'version: v1.0.0', $theme), 'version_invalid', 'version', 'SemVer'];
        yield 'version build metadata' => [str_replace('version: 1.0.0', 'version: 1.0.0+build', $theme), 'version_invalid', 'version', 'SemVer'];
        yield 'version leading zero' => [str_replace('version: 1.0.0', 'version: 1.01.0', $theme), 'version_invalid', 'version', 'SemVer'];
        yield 'version float' => [str_replace('version: 1.0.0', 'version: 1.0', $theme), 'field_type', 'version', 'must be a string'];
        yield 'constraint garbage' => [str_replace('^0.1.13', 'latest', $theme), 'constraint_invalid', 'yiipress', '"latest"'];
        yield 'constraint star' => [str_replace('^0.1.13', '"*"', $theme), 'constraint_invalid', 'yiipress', 'real version range'];
        yield 'constraint branch' => [str_replace('^0.1.13', 'dev-master', $theme), 'constraint_invalid', 'yiipress', 'real version range'];
        yield 'constraint stability' => [str_replace('^0.1.13', '^0.1@dev', $theme), 'constraint_invalid', 'yiipress', 'real version range'];
        yield 'author url http' => [str_replace('url: https://example.com', 'url: http://example.com', $theme), 'url_invalid', 'authors[0].url', 'https://'];
        yield 'homepage not url' => [$theme . "\nhomepage: https://", 'url_invalid', 'homepage', 'https://'];
        yield 'demo url' => [str_replace('  fallback: minimal', '  demo: ftp://x', $theme), 'url_invalid', 'theme.demo', 'https://'];
        yield 'author email' => [str_replace('[{name: Example Team}]', '[{name: A, email: nope}]', $processor), 'email_invalid', 'authors[0].email', '"nope"'];
        yield 'fallback value' => [str_replace('fallback: minimal', 'fallback: classic', $theme), 'value_invalid', 'theme.fallback', '"classic"'];
        yield 'stage value' => [str_replace('[before_markdown]', '[after_html]', $processor), 'value_invalid', 'processor.entry_points[0].stages[0]', '"after_html"'];
        yield 'os value' => [$theme . "\nplatforms: {os: [bsd]}", 'value_invalid', 'platforms.os[0]', '"bsd"'];
        yield 'arch value' => [$theme . "\nplatforms: {arch: [x86]}", 'value_invalid', 'platforms.arch[0]', '"x86"'];
        yield 'empty os list' => [$theme . "\nplatforms: {os: []}", 'field_invalid', 'platforms.os', 'must not be empty'];
        yield 'duplicate arch' => [$theme . "\nplatforms: {arch: [arm64, arm64]}", 'field_duplicate', 'platforms.arch[1]', 'repeats'];
        yield 'screenshot path' => [str_replace('[screenshots/home.png]', '[../home.png]', $theme), 'path_invalid', 'theme.screenshots[0]', '".." segments'];
        yield 'screenshot extension' => [str_replace('[screenshots/home.png]', '[home.gif]', $theme), 'path_invalid', 'theme.screenshots[0]', '.png, .jpg, .jpeg or .webp'];
        yield 'too many screenshots' => [str_replace('[screenshots/home.png]', '[' . self::pathList(11, 'png') . ']', $theme), 'field_invalid', 'theme.screenshots', 'at most 10 items'];
        yield 'intended use too long' => [str_replace('  fallback: minimal', '  intended_use: ' . str_repeat('u', 201), $theme), 'field_invalid', 'theme.intended_use', '1 to 200 characters'];
        yield 'processor missing' => [self::without($processor, 'processor'), 'field_missing', 'processor', 'is required'];
        yield 'processor not mapping' => [self::without($processor, 'processor') . "\nprocessor: [a]", 'not_mapping', 'processor', 'must be a mapping'];
        yield 'no entry points' => [self::without($processor, 'processor') . "\nprocessor: {entry_points: []}", 'field_invalid', 'processor.entry_points', '1 to 50 items'];
        yield 'entry path' => [str_replace('path: emoji.php', 'path: /emoji.php', $processor), 'path_invalid', 'processor.entry_points[0].path', 'must be relative'];
        yield 'entry not php' => [str_replace('path: emoji.php', 'path: emoji.inc', $processor), 'entry_not_php', 'processor.entry_points[0].path', '.php'];
        yield 'duplicate entry ignoring case' => [str_replace("    - path: emoji.php\n", "    - path: emoji.php\n      stages: [before_markdown]\n    - path: Emoji.PHP\n", $processor), 'field_duplicate', 'processor.entry_points[1].path', 'repeats'];
        yield 'empty stages' => [str_replace('[before_markdown]', '[]', $processor), 'field_invalid', 'processor.entry_points[0].stages', 'must not be empty'];
        yield 'duplicate stage' => [str_replace('[before_markdown]', '[before_markdown, before_markdown]', $processor), 'field_duplicate', 'processor.entry_points[0].stages[1]', 'repeats'];
        yield 'example missing output' => [$processor . "\n  examples: [{input: in.md}]", 'field_missing', 'processor.examples[0].output', 'is required'];
    }

    #[DataProvider('invalidManifestDataProvider')]
    public function testReportsInvalidManifest(string $yaml, string $code, string $field, string $messagePart): void
    {
        $error = self::findError(self::errorsOf($yaml), $code, $field);

        assertStringContainsString($messagePart, $error->message);
    }

    public function testSkipsTypeSpecificChecksWhenTypeIsInvalid(): void
    {
        $yaml = str_replace(['type: theme', 'fallback: minimal'], ['type: skin', 'fallback: classic'], self::THEME);

        $errors = self::errorsOf($yaml);

        assertSame(['type_invalid'], array_map(static fn(ManifestError $e) => $e->code, $errors));
    }

    public function testStopsAtUnsupportedManifestVersion(): void
    {
        $errors = self::errorsOf(str_replace('manifest: 1', 'manifest: 2', self::THEME) . "\nnew_field: x");

        assertSame(['manifest_unsupported'], array_map(static fn(ManifestError $e) => $e->code, $errors));
    }

    public function testMessageNamesFieldAndEndsWithPeriod(): void
    {
        $error = self::findError(self::errorsOf(str_replace('fallback: minimal', 'fallback: classic', self::THEME)), 'value_invalid', 'theme.fallback');

        assertSame('theme.fallback: "classic" is not allowed; use one of: minimal, none.', $error->message);
    }

    /**
     * @return list<ManifestError>
     */
    private static function errorsOf(string $yaml): array
    {
        try {
            new ManifestParser()->parse($yaml);
        } catch (InvalidManifestException $exception) {
            return $exception->errors();
        }

        self::fail('Expected an invalid manifest.');
    }

    /**
     * @param list<ManifestError> $errors
     */
    private static function findError(array $errors, string $code, string $field): ManifestError
    {
        foreach ($errors as $error) {
            if ($error->code === $code && $error->field === $field) {
                return $error;
            }
        }

        self::fail(sprintf(
            'No %s error for "%s". Got: %s',
            $code,
            $field,
            implode('; ', array_map(static fn(ManifestError $e) => "$e->code $e->field: $e->message", $errors)),
        ));
    }

    /**
     * Removes a top-level key with its indented block from a YAML string.
     */
    private static function without(string $yaml, string $key): string
    {
        return (string) preg_replace('/^' . preg_quote($key, '/') . ':.*\n?(?:^[ ].*\n?)*/m', '', $yaml);
    }

    private static function pathList(int $count, string $extension): string
    {
        return implode(', ', array_map(static fn(int $i) => "s$i.$extension", range(1, $count)));
    }

    private static function manyNodes(): string
    {
        return "a: &a [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]\n"
            . "b: &b [*a, *a, *a, *a, *a, *a, *a, *a, *a, *a]\n"
            . "c: &c [*b, *b, *b, *b, *b, *b, *b, *b, *b, *b]\n"
            . "d: [*c, *c, *c, *c, *c, *c, *c, *c, *c, *c]\n";
    }
}
