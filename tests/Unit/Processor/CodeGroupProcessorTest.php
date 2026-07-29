<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Processor;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use YiiPress\Content\Model\Entry;
use YiiPress\Processor\MarkdownProcessor;
use YiiPress\Processor\Shortcode\CodeGroupProcessor;
use YiiPress\Render\MarkdownRenderer;

final class CodeGroupProcessorTest extends TestCase
{
    public function testRendersAccessibleGroupAcrossMarkdownPass(): void
    {
        $processor = new CodeGroupProcessor();
        $markdown = <<<'MARKDOWN'
[code-group]
[code-tab label="npm"]
```sh
npm install
```
[/code-tab]
[code-tab label="pnpm"]
```sh
pnpm install
```
[/code-tab]
[/code-group]
MARKDOWN;

        $preserved = $processor->process($markdown, $this->entry());
        $html = (new MarkdownProcessor(new MarkdownRenderer()))->process($preserved, $this->entry());
        $result = $processor->process($html, $this->entry());

        self::assertStringContainsString('class="code-group"', $result);
        self::assertStringContainsString('role="tablist"', $result);
        self::assertStringContainsString('role="tab"', $result);
        self::assertStringContainsString('role="tabpanel"', $result);
        self::assertStringContainsString('aria-selected="true"', $result);
        self::assertStringContainsString('<code class="language-sh">npm install', $result);
        self::assertStringContainsString('<code class="language-sh">pnpm install', $result);
    }

    public function testSupportsConfiguredShortcodeNames(): void
    {
        $processor = new CodeGroupProcessor('switcher', 'option');
        $markdown = <<<'MARKDOWN'
[switcher]
[option label="PHP"]
```php
echo 1;
```
[/option]
[option label="Go"]
```go
fmt.Println(1)
```
[/option]
[/switcher]
MARKDOWN;

        $preserved = $processor->process($markdown, $this->entry());

        self::assertStringContainsString('yiipress-code-group:start', $preserved);
        self::assertStringNotContainsString('[switcher]', $preserved);
    }

    public function testRequiresTwoLabeledTabs(): void
    {
        $processor = new CodeGroupProcessor();
        $content = "[code-group]\n[code-tab label=\"Only\"]\n```text\none\n```\n[/code-tab]\n[/code-group]";

        self::assertSame($content, $processor->process($content, $this->entry()));
    }

    public function testDoesNotDiscardContentOutsideTabs(): void
    {
        $processor = new CodeGroupProcessor();
        $content = "[code-group]\nKeep me\n"
            . "[code-tab label=\"One\"]\n```text\none\n```\n[/code-tab]\n"
            . "[code-tab label=\"Two\"]\n```text\ntwo\n```\n[/code-tab]\n[/code-group]";

        self::assertSame($content, $processor->process($content, $this->entry()));
    }

    public function testEscapesLabelsAndCreatesIndependentGroupIds(): void
    {
        $processor = new CodeGroupProcessor();
        $group = static fn(string $label): string => "[code-group]\n"
            . "[code-tab label=\"$label\"]\n```text\none\n```\n[/code-tab]\n"
            . "[code-tab label=\"Two\"]\n```text\ntwo\n```\n[/code-tab]\n[/code-group]";
        $preserved = $processor->process($group('<b>One</b>') . "\n" . $group('Three'), $this->entry());
        $html = (new MarkdownProcessor(new MarkdownRenderer()))->process($preserved, $this->entry());
        $result = $processor->process($html, $this->entry());

        self::assertStringContainsString('&lt;b&gt;One&lt;/b&gt;', $result);
        self::assertStringContainsString('id="code-group-1-tab-1"', $result);
        self::assertStringContainsString('id="code-group-2-tab-1"', $result);
    }

    public function testProvidesAssetsOnlyForRenderedGroups(): void
    {
        $processor = new CodeGroupProcessor();

        self::assertSame('', $processor->headAssets('<p>Plain</p>'));
        self::assertStringContainsString('code-groups.css', $processor->headAssets('<div class="code-group"></div>'));
        self::assertCount(2, $processor->assetFiles());
    }

    public function testBrowserAssetsUseDelegationKeyboardNavigationAndProgressiveEnhancement(): void
    {
        $directory = dirname(__DIR__, 3) . '/src/Processor/Shortcode/assets/';
        $script = file_get_contents($directory . 'code-groups.js');
        $style = file_get_contents($directory . 'code-groups.css');

        self::assertNotFalse($script);
        self::assertNotFalse($style);
        self::assertStringContainsString("document.addEventListener('click'", $script);
        self::assertStringContainsString("document.addEventListener('keydown'", $script);
        self::assertStringContainsString("'ArrowLeft', 'ArrowRight', 'Home', 'End'", $script);
        self::assertStringContainsString("group.classList.add('is-enhanced')", $script);
        self::assertStringContainsString('.code-group.is-enhanced .code-group-panel[hidden]', $style);
    }

    private function entry(): Entry
    {
        return new Entry(
            filePath: __FILE__,
            collection: 'docs',
            slug: 'test',
            title: 'Test',
            date: new DateTimeImmutable('2026-01-01'),
            draft: false,
            tags: [],
            categories: [],
            authors: [],
            summary: '',
            permalink: '/docs/test/',
            layout: '',
            theme: '',
            weight: 0,
            language: 'en',
            redirectTo: '',
            extra: [],
            bodyOffset: 0,
            bodyLength: 0,
        );
    }
}
