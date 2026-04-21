<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Author;
use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\Model\Navigation;
use App\Content\Model\SiteConfig;
use App\Content\PermalinkResolver;
use App\Render\MarkdownRenderer;
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
    ): int {
        if ($authors === []) {
            return 0;
        }

        $renderer = new PageTemplateRenderer($this->templateResolver, $siteConfig->theme, $this->assetManifest);

        $this->writeIndex($siteConfig, $authors, $outputDir, $navigation);
        $pageCount = 1;

        foreach ($authors as $slug => $author) {
            $entries = $entriesByAuthor[$slug] ?? [];
            $this->writeAuthor($siteConfig, $author, $entries, $collections, $outputDir, $navigation, $renderer);
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
    ): void {
        $this->writeIndexPage(
            new PageTemplateRenderer($this->templateResolver, $siteConfig->theme, $this->assetManifest),
            $siteConfig,
            $authors,
            $outputDir,
            $navigation,
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
    ): void {
        $this->writeAuthorPage(
            $renderer ?? new PageTemplateRenderer($this->templateResolver, $siteConfig->theme, $this->assetManifest),
            $siteConfig,
            $author,
            $entries,
            $collections,
            $outputDir,
            $navigation,
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
    ): void {
        $rootPath = RelativePathHelper::rootPath('/authors/');
        $uiViewData = UiViewData::forSite($siteConfig, $this->templateResolver, $siteConfig->theme);

        $authorList = [];
        foreach ($authors as $slug => $author) {
            $authorList[] = [
                'title' => $author->title,
                'url' => $rootPath . 'authors/' . $slug . '/',
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

        $dir = $outputDir . '/authors';
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        file_put_contents($dir . '/index.html', $html);
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
    ): void {
        $rootPath = RelativePathHelper::rootPath('/authors/' . $author->slug . '/');
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
                ? RelativePathHelper::relativize(PermalinkResolver::resolve($entry, $collection), $rootPath)
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

        $dir = $outputDir . '/authors/' . $author->slug;
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        file_put_contents($dir . '/index.html', $html);
    }
}
