<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Processor;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use YiiPress\Content\Model\Entry;
use YiiPress\Processor\ContentProcessorPipeline;
use YiiPress\Processor\MarkdownProcessor;
use YiiPress\Processor\Shortcode\CodeGroupProcessor;
use YiiPress\Render\MarkdownRenderer;

final class CodeGroupProcessorTest extends TestCase
{
    public function testRendersLabeledFallbackGroupAcrossMarkdownPass(): void
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
        $html = new MarkdownProcessor(new MarkdownRenderer())->process($preserved, $this->entry());
        $result = $processor->process($html, $this->entry());

        self::assertStringContainsString('class="code-group"', $result);
        self::assertStringContainsString('class="code-group-label"', $result);
        self::assertStringContainsString('data-label="npm"', $result);
        self::assertStringContainsString('data-label="pnpm"', $result);
        self::assertStringNotContainsString('role="tab"', $result);
        self::assertStringNotContainsString('<button', $result);
        self::assertStringContainsString('<code class="language-sh">npm install', $result);
        self::assertStringContainsString('<code class="language-sh">pnpm install', $result);
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

    public function testUnrelatedCodeGroupCommentDoesNotSkipShortcodePreservation(): void
    {
        $processor = new CodeGroupProcessor();
        $content = "<!-- yiipress-code-group:note -->\n"
            . "[code-group]\n"
            . "[code-tab label=\"One\"]\n```text\none\n```\n[/code-tab]\n"
            . "[code-tab label=\"Two\"]\n```text\ntwo\n```\n[/code-tab]\n"
            . '[/code-group]';

        $result = $processor->process($content, $this->entry());

        self::assertStringContainsString('<!-- yiipress-code-group:note -->', $result);
        self::assertStringContainsString('<!-- yiipress-code-group:start -->', $result);
        self::assertStringNotContainsString('[code-group]', $result);
    }

    public function testEscapesLabelsAndCreatesIndependentGroupIds(): void
    {
        $processor = new CodeGroupProcessor();
        $group = static fn(string $label): string => "[code-group]\n"
            . "[code-tab label=\"$label\"]\n```text\none\n```\n[/code-tab]\n"
            . "[code-tab label=\"Two\"]\n```text\ntwo\n```\n[/code-tab]\n[/code-group]";
        $preserved = $processor->process($group('<b>One</b>') . "\n" . $group('Three'), $this->entry());
        $html = new MarkdownProcessor(new MarkdownRenderer())->process($preserved, $this->entry());
        $result = $processor->process($html, $this->entry());

        self::assertStringContainsString('&lt;b&gt;One&lt;/b&gt;', $result);
        self::assertStringContainsString('id="code-group-1-panel-1"', $result);
        self::assertStringContainsString('id="code-group-2-panel-1"', $result);
    }

    public function testProvidesAssetsOnlyForRenderedGroups(): void
    {
        $processor = new CodeGroupProcessor();

        self::assertSame('', $processor->headAssets('<p>Plain</p>'));
        self::assertStringContainsString('code-groups.css', $processor->headAssets('<div class="code-group"></div>'));
        self::assertCount(2, $processor->assetFiles());
    }

    public function testProvidesRootRelativePluginAssetsForNestedPages(): void
    {
        $processor = new CodeGroupProcessor();
        $processor->applyRootPath('../../');

        $assets = $processor->headAssets('<div class="code-group"></div>');

        self::assertStringContainsString('href="../../assets/plugins/code-groups.css"', $assets);
        self::assertStringContainsString('src="../../assets/plugins/code-groups.js"', $assets);
    }

    public function testEscapesPluginAssetUrls(): void
    {
        $processor = new CodeGroupProcessor();
        $processor->applyRootPath('./\"><script>alert(1)</script>');

        $assets = $processor->headAssets('<div class="code-group"></div>');

        self::assertStringNotContainsString('<script>alert(1)</script>', $assets);
        self::assertStringContainsString('&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;', $assets);
    }

    public function testPipelineResetsRootPathWhenNextPageDoesNotProvideOne(): void
    {
        $processor = new CodeGroupProcessor();
        $pipeline = new ContentProcessorPipeline($processor);

        $pipeline->process('nested', $this->entry(), '../../');
        $pipeline->process('root', $this->entry());

        $assets = $pipeline->collectHeadAssets('<div class="code-group"></div>');

        self::assertStringContainsString('href="./assets/plugins/code-groups.css"', $assets);
        self::assertStringContainsString('src="./assets/plugins/code-groups.js"', $assets);
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
        self::assertStringContainsString("document.createElement('button')", $script);
        self::assertStringContainsString("panel.setAttribute('role', 'tabpanel')", $script);
        self::assertStringContainsString("group.classList.add('is-enhanced')", $script);
        self::assertStringContainsString("if (!tab)", $script);
        self::assertStringContainsString('.code-group.is-enhanced .code-group-panel[hidden]', $style);
        self::assertStringContainsString('.code-group-tabs [role="tab"]:hover', $style);
        self::assertStringContainsString('.code-group-tabs [role="tab"][aria-selected="true"]', $style);
        self::assertStringContainsString('.code-group-tabs [role="tab"]:focus-visible', $style);
        self::assertStringContainsString('overflow-y: hidden;', $style);
        self::assertStringContainsString('@media (prefers-reduced-motion: reduce)', $style);
        self::assertStringContainsString('transition: none;', $style);
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
