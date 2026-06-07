<?php

declare(strict_types=1);

namespace YiiPress\Build;

use YiiPress\Content\Model\Author;
use YiiPress\Content\Model\Collection;
use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\Navigation;
use YiiPress\Content\Model\SiteConfig;
use YiiPress\Content\PermalinkResolver;
use YiiPress\Render\MarkdownRenderer;
use RuntimeException;

final class AuthorPageWriter
{
    private ?MarkdownRenderer $markdownRenderer = null;

    public function __construct(
        private readonly TemplateResolver $templateResolver,
        private readonly ?AssetFingerprintManifest $assetManifest = null,
    ) {}

    /**
     * @param array<string, Author> $authors
     * @param array<string, list<Entry>> $entriesByAuthor
     * @param array<string, Collection> $collections
     */
    public function write(
        SiteConfig $siteConfig,
        array $authors,
        array $entriesByAuthor,
        array $collections,
        string $outputDir,
        ?Navigation $navigation = null,
        bool $noWrite = false,
    ): int {
        if ($authors === []) {
            return 0;
        }

        $renderer = $this->createPageTemplateRenderer($siteConfig);

        $this->writeIndex($siteConfig, $authors, $outputDir, $navigation, $noWrite);
        $pageCount = 1;

        foreach ($authors as $slug => $author) {
            $entries = $entriesByAuthor[$slug] ?? [];
            $this->writeAuthor($siteConfig, $author, $entries, $collections, $outputDir, $navigation, $renderer, $noWrite);
            $pageCount++;
        }

        return $pageCount;
    }

    /**
     * @param array<string, Author> $authors
     */
    public function writeIndex(
        SiteConfig $siteConfig,
        array $authors,
        string $outputDir,
        ?Navigation $navigation = null,
        bool $noWrite = false,
    ): void {
        $this->writeIndexPage(
            $this->createPageTemplateRenderer($siteConfig),
            $siteConfig,
            $authors,
            $outputDir,
            $navigation,
            $noWrite,
        );
    }

    /**
     * @param list<Entry> $entries
     * @param array<string, Collection> $collections
     */
    public function writeAuthor(
        SiteConfig $siteConfig,
        Author $author,
        array $entries,
        array $collections,
        string $outputDir,
        ?Navigation $navigation = null,
        ?PageTemplateRenderer $renderer = null,
        bool $noWrite = false,
    ): void {
        $this->writeAuthorPage(
            $renderer ?? $this->createPageTemplateRenderer($siteConfig),
            $siteConfig,
            $author,
            $entries,
            $collections,
            $outputDir,
            $navigation,
            $noWrite,
        );
    }

    private function createPageTemplateRenderer(SiteConfig $siteConfig): PageTemplateRenderer
    {
        return new PageTemplateRenderer(
            $this->templateResolver,
            $siteConfig->theme,
            $this->assetManifest,
            $siteConfig->minify,
        );
    }

    /**
     * @param array<string, Author> $authors
     */
    private function writeIndexPage(
        PageTemplateRenderer $renderer,
        SiteConfig $siteConfig,
        array $authors,
        string $outputDir,
        ?Navigation $navigation,
        bool $noWrite,
    ): void {
        $rootPath = UrlResolver::rootPath('/authors/');
        $uiViewData = UiViewData::forSite($siteConfig, $this->templateResolver, $siteConfig->theme);

        $authorList = [];
        foreach ($authors as $slug => $author) {
            $authorList[] = [
                'title' => $author->title,
                'url' => UrlResolver::sitePath('/authors/' . $slug . '/', $rootPath),
                'avatar' => $author->avatar,
            ];
        }

        $html = $renderer->render('author_index', [
            'siteTitle' => $siteConfig->title,
            'authorList' => $authorList,
            'nav' => $navigation,
            'rootPath' => $rootPath,
            'language' => $siteConfig->defaultLanguage,
            'metaTags' => MetaTagsBuilder::forPage($siteConfig, $uiViewData->ui->get('authors'), $siteConfig->description, '/authors/'),
            'search' => $siteConfig->search !== null,
            'searchResults' => $siteConfig->search?->results ?? 10,
        ] + $uiViewData->toArray(), $rootPath);

        if (!$noWrite) {
            $dir = $outputDir . '/authors';
            if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
            }

            file_put_contents($dir . '/index.html', $html);
        }
    }

    /**
     * @param list<Entry> $entries
     * @param array<string, Collection> $collections
     */
    private function writeAuthorPage(
        PageTemplateRenderer $renderer,
        SiteConfig $siteConfig,
        Author $author,
        array $entries,
        array $collections,
        string $outputDir,
        ?Navigation $navigation,
        bool $noWrite,
    ): void {
        $rootPath = UrlResolver::rootPath('/authors/' . $author->slug . '/');
        $uiViewData = UiViewData::forSite($siteConfig, $this->templateResolver, $siteConfig->theme);

        $authorTitle = $author->title;
        $authorEmail = $author->email;
        $authorUrl = $author->url;
        $authorAvatar = $author->avatar;
        if ($this->markdownRenderer === null) {
            $this->markdownRenderer = new MarkdownRenderer($siteConfig->markdown);
        }
        $authorBio = $this->markdownRenderer->render($author->body());

        $entryData = [];
        foreach ($entries as $entry) {
            $collection = $collections[$entry->collection] ?? null;
            $url = $collection !== null
                ? UrlResolver::sitePath(PermalinkResolver::resolve($entry, $collection, $siteConfig->i18n), $rootPath)
                : '#';

            $entryData[] = [
                'title' => $entry->title,
                'url' => $url,
                'date' => $entry->date?->format($siteConfig->dateFormat) ?? '',
            ];
        }

        $entries = $entryData;

        $html = $renderer->render('author', [
            'siteTitle' => $siteConfig->title,
            'authorTitle' => $authorTitle,
            'authorEmail' => $authorEmail,
            'authorUrl' => $authorUrl,
            'authorAvatar' => $authorAvatar,
            'authorBio' => $authorBio,
            'entries' => $entries,
            'nav' => $navigation,
            'rootPath' => $rootPath,
            'language' => $siteConfig->defaultLanguage,
            'metaTags' => MetaTagsBuilder::forPage($siteConfig, $author->title, $siteConfig->description, '/authors/' . $author->slug . '/'),
            'search' => $siteConfig->search !== null,
            'searchResults' => $siteConfig->search?->results ?? 10,
        ] + $uiViewData->toArray(), $rootPath);

        if (!$noWrite) {
            $dir = $outputDir . '/authors/' . $author->slug;
            if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
            }

            file_put_contents($dir . '/index.html', $html);
        }
    }
}
