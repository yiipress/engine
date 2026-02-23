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

final class AuthorPageWriter
{
    private ?MarkdownRenderer $markdownRenderer = null;

    public function __construct(private readonly TemplateResolver $templateResolver) {}

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

        $this->writeIndexPage($siteConfig, $authors, $outputDir, $navigation);
        $pageCount = 1;

        foreach ($authors as $slug => $author) {
            $entries = $entriesByAuthor[$slug] ?? [];
            $this->writeAuthorPage($siteConfig, $author, $entries, $collections, $outputDir, $navigation);
            $pageCount++;
        }

        return $pageCount;
    }

    /**
     * @param array<string, Author> $authors
     */
    private function writeIndexPage(
        SiteConfig $siteConfig,
        array $authors,
        string $outputDir,
        ?Navigation $navigation,
    ): void {
        $siteTitle = $siteConfig->title;
        $nav = $navigation;
        $partial = new TemplateContext($this->templateResolver, $siteConfig->theme)->partial(...);

        $rootPath = RelativePathHelper::rootPath('/authors/');

        $authorList = [];
        foreach ($authors as $slug => $author) {
            $authorList[] = [
                'title' => $author->title,
                'url' => $rootPath . 'authors/' . $slug . '/',
                'avatar' => $author->avatar,
            ];
        }

        ob_start();
        require $this->templateResolver->resolve('author_index');
        $html = ob_get_clean();

        $dir = $outputDir . '/authors';
        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($dir . '/index.html', $html);
    }

    /**
     * @param list<Entry> $entries
     * @param array<string, Collection> $collections
     */
    private function writeAuthorPage(
        SiteConfig $siteConfig,
        Author $author,
        array $entries,
        array $collections,
        string $outputDir,
        ?Navigation $navigation,
    ): void {
        $siteTitle = $siteConfig->title;
        $nav = $navigation;
        $partial = new TemplateContext($this->templateResolver, $siteConfig->theme)->partial(...);
        $rootPath = RelativePathHelper::rootPath('/authors/' . $author->slug . '/');

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

        ob_start();
        require $this->templateResolver->resolve('author');
        $html = ob_get_clean();

        $dir = $outputDir . '/authors/' . $author->slug;
        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($dir . '/index.html', $html);
    }
}
