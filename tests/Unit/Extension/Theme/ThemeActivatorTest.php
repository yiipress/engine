<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Theme;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Theme\ThemeActivator;

final class ThemeActivatorTest extends TestCase
{
    #[DataProvider('edits')]
    public function testActivates(string $before, string $after): void
    {
        self::assertSame($after, new ThemeActivator()->activate($before, 'wonder'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function edits(): iterable
    {
        yield 'replace plain' => ["title: Blog\ntheme: minimal\n", "title: Blog\ntheme: wonder\n"];
        yield 'keep comment' => ["theme: minimal # was default\n", "theme: wonder # was default\n"];
        yield 'replace double quoted' => ["theme: \"my theme\"\n", "theme: wonder\n"];
        yield 'replace single quoted' => ["theme: 'it''s'  # c\n", "theme: wonder # c\n"];
        yield 'replace empty value' => ["theme:\ntitle: Blog\n", "theme: wonder\ntitle: Blog\n"];
        yield 'append' => ["title: Blog\n", "title: Blog\ntheme: wonder\n"];
        yield 'append without final newline' => ['title: Blog', "title: Blog\ntheme: wonder\n"];
        yield 'empty file' => ['', "theme: wonder\n"];
        yield 'CRLF kept' => ["title: Blog\r\ntheme: minimal\r\n", "title: Blog\r\ntheme: wonder\r\n"];
        yield 'CRLF append' => ["title: Blog\r\n", "title: Blog\r\ntheme: wonder\r\n"];
        yield 'BOM kept' => ["\xEF\xBB\xBFtitle: Blog\n", "\xEF\xBB\xBFtitle: Blog\ntheme: wonder\n"];
        yield 'other keys untouched' => [
            "# Site\nhighlight_theme: github\nmarkdown:\n  theme: x\ntheme: minimal\n",
            "# Site\nhighlight_theme: github\nmarkdown:\n  theme: x\ntheme: wonder\n",
        ];
    }

    #[DataProvider('namesYamlReadsAsOtherTypes')]
    public function testQuotesNameThatYamlWouldNotReadAsString(string $name): void
    {
        self::assertSame(
            "title: Blog\ntheme: \"$name\"\n",
            new ThemeActivator()->activate("title: Blog\ntheme: minimal\n", $name),
        );
        self::assertSame("theme: \"$name\"\n", new ThemeActivator()->activate('', $name));
    }

    /**
     * Valid theme names (`[a-z0-9-]`) that YAML 1.1 reads as a number, boolean or null.
     *
     * @return iterable<string, array{string}>
     */
    public static function namesYamlReadsAsOtherTypes(): iterable
    {
        foreach (['2026', '0', 'null', 'yes', 'no', 'on', 'off', 'true', 'y'] as $name) {
            yield $name => [$name];
        }
    }

    public function testKeepsNameThatYamlReadsAsStringPlain(): void
    {
        self::assertSame("theme: 1e3\n", new ThemeActivator()->activate('', '1e3'));
    }

    #[DataProvider('unsupported')]
    public function testRefusesUnsafeEdit(string $config): void
    {
        try {
            new ThemeActivator()->activate($config, 'wonder');
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('config_unsupported', $exception->errorCode, $exception->getMessage());
            self::assertStringContainsString('--no-activate', (string) $exception->getSolution());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupported(): iterable
    {
        yield 'two theme lines' => ["theme: a\ntheme: b\n"];
        yield 'flow mapping' => ["{title: Blog, theme: a}\n"];
        yield 'alias' => ["base: &t a\ntheme: *t\n"];
        yield 'tag' => ["theme: !!str a\n"];
        yield 'block scalar' => ["theme: |\n  a\n"];
        yield 'nested value' => ["theme:\n  name: a\n"];
        yield 'multi-line plain' => ["theme: a\n  b\n"];
        yield 'unclosed quote' => ["theme: \"a\n  b\"\n"];
        yield 'flow sequence' => ["theme: [a]\n"];
    }
}
