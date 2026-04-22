<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\AssetFingerprintManifest;
use App\Build\EntryRenderer;
use App\Build\Theme;
use App\Build\ThemeRegistry;
use App\Build\TemplateResolver;
use App\Content\Model\Entry;
use App\Content\Model\I18nConfig;
use App\Content\Model\SearchConfig;
use App\Content\Model\SiteConfig;
use App\Processor\ContentProcessorPipeline;
use DateTimeImmutable;
use FilesystemIterator;
use Locale;
use PHPUnit\Framework\TestCase;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;
use function mb_strtoupper;
use function mb_substr;

final class EntryRendererTest extends TestCase
{
    private string $contentDir;

    protected function setUp(): void
    {
        $this->contentDir = sys_get_temp_dir() . '/yiipress-renderer-test-' . uniqid();
        mkdir($this->contentDir . '/blog', 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->contentDir);
    }

    public function testRendersWithDefaultTemplate(): void
    {
        $entryFile = $this->contentDir . '/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Test Post\n---\n\nHello world.\n");

        $entry = $this->createEntry(filePath: $entryFile, title: 'Test Post');
        $renderer = new EntryRenderer($this->createPipeline(), $this->createTemplateResolver(), contentDir: $this->contentDir);
        $html = $renderer->render($this->createSiteConfig(), $entry);

        assertStringContainsString('<h1>Test Post</h1>', $html);
        assertStringContainsString('Hello world.', $html);
        assertStringContainsString("root.setAttribute('data-theme', theme);", $html);
        assertStringContainsString("root.setAttribute('data-ui-language', uiLanguage);", $html);
        assertStringContainsString('window.__yiipressApplyLanguageSelector = function (selector)', $html);
        assertStringContainsString('window.__yiipressApplyMenuTranslations = function (container)', $html);
        assertStringContainsString("root.style.colorScheme = theme;", $html);
    }

    public function testUiLanguageIsIndependentFromEntryLanguage(): void
    {
        $entryFile = $this->contentDir . '/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Test Post\nlanguage: ru\n---\n\nHello world.\n");

        $entry = $this->createEntry(filePath: $entryFile, title: 'Test Post', language: 'ru');
        $renderer = new EntryRenderer($this->createPipeline(), $this->createTemplateResolver(), contentDir: $this->contentDir);
        $html = $renderer->render(
            $this->createSiteConfig(
                search: new SearchConfig(),
                i18n: new I18nConfig(languages: ['en', 'ru'], defaultLanguage: 'en'),
            ),
            $entry,
            '/ru/blog/test-post/',
        );

        assertStringContainsString('<html lang="ru">', $html);
        assertStringContainsString('id="ui-language-selector"', $html);
        assertStringContainsString('value="en" selected>' . htmlspecialchars($this->capitalizeUtf8(Locale::getDisplayLanguage('en', 'en') ?: 'EN')) . '</option>', $html);
        assertStringContainsString('value="ru">' . htmlspecialchars($this->capitalizeUtf8(Locale::getDisplayLanguage('ru', 'ru') ?: 'RU')) . '</option>', $html);
        assertStringContainsString('aria-label="Search"', $html);
        assertStringContainsString('data-default-language="en"', $html);
        assertStringContainsString('"ui_language":"Interface language"', $html);
    }

    public function testRendersWithCustomLayout(): void
    {
        mkdir($this->contentDir . '/templates', 0o755, true);
        file_put_contents($this->contentDir . '/templates/wide.php', <<<'PHP'
<?php
/** @var string $entryTitle */
/** @var string $content */
?>
<div class="wide-layout"><h1><?= $h($entryTitle) ?></h1><?= $content ?></div>
PHP);

        $entryFile = $this->contentDir . '/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Wide Post\nlayout: wide\n---\n\nWide content.\n");

        $entry = $this->createEntry(filePath: $entryFile, title: 'Wide Post', layout: 'wide');
        $renderer = new EntryRenderer($this->createPipeline(), $this->createTemplateResolver($this->contentDir . '/templates'), contentDir: $this->contentDir);
        $html = $renderer->render($this->createSiteConfig(theme: 'custom'), $entry);

        assertStringContainsString('wide-layout', $html);
        assertStringContainsString('<h1>Wide Post</h1>', $html);
        assertStringContainsString('Wide content.', $html);
    }

    public function testProvidesTranslationHelperToCustomEntryTemplates(): void
    {
        mkdir($this->contentDir . '/templates', 0o755, true);
        file_put_contents($this->contentDir . '/templates/translated.php', <<<'PHP'
<?php
/** @var Closure(string, array): string $t */
?>
<div><?= $h($t('search')) ?></div>
PHP);

        $entryFile = $this->contentDir . '/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Translated Post\nlayout: translated\n---\n\nBody.\n");

        $entry = $this->createEntry(filePath: $entryFile, title: 'Translated Post', layout: 'translated');
        $renderer = new EntryRenderer($this->createPipeline(), $this->createTemplateResolver($this->contentDir . '/templates'), contentDir: $this->contentDir);
        $html = $renderer->render($this->createSiteConfig(theme: 'custom'), $entry);

        assertStringContainsString('<div>Search</div>', $html);
    }

    public function testFallsBackToDefaultWhenLayoutFileNotFound(): void
    {
        $entryFile = $this->contentDir . '/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Missing Layout\nlayout: nonexistent\n---\n\nContent.\n");

        $entry = $this->createEntry(filePath: $entryFile, title: 'Missing Layout', layout: 'nonexistent');
        $renderer = new EntryRenderer($this->createPipeline(), $this->createTemplateResolver(), contentDir: $this->contentDir);
        $html = $renderer->render($this->createSiteConfig(), $entry);

        assertStringContainsString('<h1>Missing Layout</h1>', $html);
        assertStringContainsString('Content.', $html);
    }

    public function testTagsNotInContentArePassedToTemplate(): void
    {
        mkdir($this->contentDir . '/templates', 0o755, true);
        file_put_contents($this->contentDir . '/templates/tags.php', <<<'PHP'
<?php /** @var list<string> $tags */ ?>
<?php foreach ($tags as $tag): ?><span class="tag"><?= $h($tag) ?></span><?php endforeach; ?>
PHP);

        $entryFile = $this->contentDir . '/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Post\nlayout: tags\n---\n\nContent without inline tags.\n");

        $entry = $this->createEntry(filePath: $entryFile, title: 'Post', layout: 'tags', tags: ['php', 'yii']);
        $renderer = new EntryRenderer($this->createPipeline(), $this->createTemplateResolver($this->contentDir . '/templates'), contentDir: $this->contentDir);
        $html = $renderer->render($this->createSiteConfig(theme: 'custom'), $entry);

        assertStringContainsString('php', $html);
        assertStringContainsString('yii', $html);
    }

    public function testInlineTagsAreFilteredFromTagsList(): void
    {
        mkdir($this->contentDir . '/templates', 0o755, true);
        file_put_contents($this->contentDir . '/templates/tags2.php', <<<'PHP'
<?php /** @var list<string> $tags */ ?>
<?php foreach ($tags as $tag): ?><span class="tag"><?= $h($tag) ?></span><?php endforeach; ?>
PHP);

        $entryFile = $this->contentDir . '/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Post\nlayout: tags2\n---\n\nContent with #php inline.\n");

        $entry = $this->createEntry(filePath: $entryFile, title: 'Post', layout: 'tags2', tags: ['php', 'yii']);
        $renderer = new EntryRenderer($this->createPipeline(), $this->createTemplateResolver($this->contentDir . '/templates'), contentDir: $this->contentDir);
        $html = $renderer->render($this->createSiteConfig(theme: 'custom'), $entry);

        assertStringNotContainsString('"tag">php', $html);
        assertStringContainsString('"tag">yii', $html);
    }

    public function testCustomLayoutReceivesAllVariables(): void
    {
        mkdir($this->contentDir . '/templates', 0o755, true);
        file_put_contents($this->contentDir . '/templates/full.php', <<<'PHP'
<?php
/** @var string $siteTitle */
/** @var string $entryTitle */
/** @var string $content */
/** @var string $date */
/** @var string $author */
/** @var string $collection */
?>
<div data-site="<?= $h($siteTitle) ?>" data-date="<?= $h($date) ?>" data-author="<?= $h($author) ?>" data-collection="<?= $h($collection) ?>"><?= $content ?></div>
PHP);

        $entryFile = $this->contentDir . '/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Full Post\nlayout: full\n---\n\nBody.\n");

        $entry = $this->createEntry(
            filePath: $entryFile,
            title: 'Full Post',
            layout: 'full',
            collection: 'blog',
            authors: ['alice'],
        );
        $renderer = new EntryRenderer($this->createPipeline(), $this->createTemplateResolver($this->contentDir . '/templates'), contentDir: $this->contentDir);
        $html = $renderer->render($this->createSiteConfig(theme: 'custom'), $entry);

        assertStringContainsString('data-site="Test Site"', $html);
        assertStringContainsString('data-date="2024-01-01"', $html);
        assertStringContainsString('data-author="alice"', $html);
        assertStringContainsString('data-collection="blog"', $html);
    }

    public function testRewritesFingerprintedAssetUrlsInRenderedHtml(): void
    {
        mkdir($this->contentDir . '/templates', 0o755, true);
        file_put_contents($this->contentDir . '/templates/assets.php', <<<'PHP'
<?php
use App\Build\Asset;
use App\Build\AssetFingerprintManifest;
?>
<link rel="stylesheet" href="<?= Asset::url('assets/theme/style.css', $rootPath, $assetManifest) ?>">
<script src="../../assets/theme/image-zoom.js"></script>
PHP);

        $entryFile = $this->contentDir . '/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Asset Post\nlayout: assets\n---\n\nBody.\n");

        $manifest = new AssetFingerprintManifest();
        $manifest->register('assets/theme/style.css', dirname(__DIR__, 3) . '/themes/minimal/assets/style.css');
        $imageZoomTarget = $manifest->register('assets/theme/image-zoom.js', dirname(__DIR__, 3) . '/themes/minimal/assets/image-zoom.js');

        $entry = $this->createEntry(filePath: $entryFile, title: 'Asset Post', layout: 'assets');
        $renderer = new EntryRenderer(
            $this->createPipeline(),
            $this->createTemplateResolver($this->contentDir . '/templates'),
            contentDir: $this->contentDir,
            assetManifest: $manifest,
        );
        $html = $renderer->render($this->createSiteConfig(theme: 'custom'), $entry, '/blog/post/');

        assertStringContainsString($imageZoomTarget, $html);
        assertStringNotContainsString('../../assets/theme/image-zoom.js', $html);
    }

    public function testRendersSearchUiWhenSearchEnabled(): void
    {
        $entryFile = $this->contentDir . '/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Search Post\n---\n\nSearch body.\n");

        $entry = $this->createEntry(filePath: $entryFile, title: 'Search Post');
        $renderer = new EntryRenderer($this->createPipeline(), $this->createTemplateResolver(), contentDir: $this->contentDir);
        $html = $renderer->render($this->createSiteConfig(search: new SearchConfig()), $entry, '/blog/search-post/');

        assertStringContainsString('id="search-modal"', $html);
        assertStringContainsString('aria-controls="search-modal"', $html);
        assertStringContainsString('id="search-close"', $html);
        assertStringContainsString('data-ui-attr-aria-label="search_close"', $html);
        assertStringContainsString('<span class="search-hint" aria-hidden="true">ESC</span>', $html);
        assertStringContainsString('assets/theme/search.css', $html);
        assertStringContainsString('assets/theme/search.js', $html);
    }

    private function createPipeline(): ContentProcessorPipeline
    {
        return new ContentProcessorPipeline();
    }

    private function createTemplateResolver(string $extraThemePath = ''): TemplateResolver
    {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('minimal', dirname(__DIR__, 3) . '/themes/minimal'));
        if ($extraThemePath !== '') {
            $registry->register(new Theme('custom', $extraThemePath));
        }
        return new TemplateResolver($registry);
    }

    private function createSiteConfig(string $theme = '', ?SearchConfig $search = null, ?I18nConfig $i18n = null): SiteConfig
    {
        return new SiteConfig(
            title: 'Test Site',
            description: '',
            baseUrl: 'https://example.com',
            defaultLanguage: 'en',
            charset: 'UTF-8',
            defaultAuthor: '',
            dateFormat: 'Y-m-d',
            entriesPerPage: 10,
            permalink: '/:collection/:slug/',
            taxonomies: [],
            params: [],
            theme: $theme,
            search: $search,
            i18n: $i18n,
        );
    }

    /**
     * @param list<string> $authors
     * @param list<string> $tags
     */
    private function createEntry(
        string $filePath,
        string $title = 'Post',
        string $layout = '',
        string $collection = 'blog',
        array $authors = [],
        array $tags = [],
        string $language = '',
    ): Entry {
        $content = file_get_contents($filePath);
        $bodyMarker = "---\n\n";
        $bodyPos = strpos($content, $bodyMarker, 4);
        $bodyOffset = $bodyPos !== false ? $bodyPos + strlen($bodyMarker) : 0;
        $bodyLength = strlen($content) - $bodyOffset;

        $body = $bodyLength > 0 ? substr($content, $bodyOffset, $bodyLength) : '';
        preg_match_all('/#([\w-]+)/u', strip_tags($body), $inlineMatches);
        $inlineTags = array_map(strtolower(...), $inlineMatches[1]);

        return new Entry(
            filePath: $filePath,
            collection: $collection,
            slug: 'post',
            title: $title,
            date: new DateTimeImmutable('2024-01-01'),
            draft: false,
            tags: $tags,
            categories: [],
            authors: $authors,
            summary: '',
            permalink: '',
            layout: $layout,
            theme: '',
            weight: 0,
            language: $language,
            redirectTo: '',
            extra: [],
            bodyOffset: $bodyOffset,
            bodyLength: $bodyLength,
            inlineTags: $inlineTags,
        );
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
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
        rmdir($path);
    }

    private function capitalizeUtf8(string $value): string
    {
        $firstCharacter = mb_substr($value, 0, 1);
        if ($firstCharacter === '') {
            return $value;
        }

        return mb_strtoupper($firstCharacter) . mb_substr($value, 1);
    }
}
