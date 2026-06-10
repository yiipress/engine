<?php

declare(strict_types=1);

namespace YiiPress\Build;

use YiiPress\Content\CrossReferenceResolver;
use YiiPress\Content\I18n\TranslationIndex;
use YiiPress\Content\Model\Author;
use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\Navigation;
use YiiPress\Content\Model\SiteConfig;
use YiiPress\Content\Related\RelatedIndex;
use YiiPress\Hook\RenderFinishedEvent;
use YiiPress\Hook\RenderStartedEvent;
use YiiPress\Processor\ContentProcessorPipeline;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

use function date_default_timezone_get;
use function dirname;
use function filemtime;
use function hash;
use function strlen;

final class EntryRenderer
{
    /** @var array<string, Closure> */
    private array $templateClosures = [];

    /** @var array<string, TemplateContext> */
    private array $templateContexts = [];

    /** @var array<string, Closure> */
    private array $partialClosures = [];

    /**
     * @param array<string, Author> $authors
     */
    public function __construct(
        private readonly ContentProcessorPipeline $pipeline,
        private readonly TemplateResolver $templateResolver,
        private readonly ?BuildCache $cache = null,
        private readonly string $contentDir = '',
        private readonly array $authors = [],
        private readonly ?AssetFingerprintManifest $assetManifest = null,
        private readonly ?RelatedIndex $relatedIndex = null,
        private readonly ?TranslationIndex $translationIndex = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    public function render(
        SiteConfig $siteConfig,
        Entry $entry,
        string $permalink = '',
        ?Navigation $navigation = null,
        ?CrossReferenceResolver $crossRefResolver = null,
        ?array $navigationPager = null,
    ): string {
        $this->eventDispatcher?->dispatch(new RenderStartedEvent($siteConfig, $entry, $permalink));

        $cacheContext = '';
        if ($this->cache !== null) {
            $cacheContext = $this->cacheContext($siteConfig, $entry, $permalink, $navigation, $crossRefResolver, $navigationPager);
            $cached = $this->cache->get($entry->filePath, $cacheContext);
            if ($cached !== null) {
                $html = $this->dispatchRenderFinished($siteConfig, $entry, $permalink, $cached);

                return $html === $cached ? $html : $this->minify($siteConfig, $html);
            }
        }

        $body = str_replace('[cut]', '', $entry->body());
        if ($crossRefResolver !== null) {
            $resolver = $crossRefResolver->withCurrentDir($this->resolveContentDir($entry));
            if ($permalink !== '') {
                $resolver = $resolver->withCurrentPermalink($permalink);
            }
            $body = $resolver->resolve($body);
        }
        $rootPath = UrlResolver::rootPath($permalink);
        $content = $this->pipeline->process($body, $entry, $rootPath);
        $headAssets = $this->pipeline->collectHeadAssets($content);
        $toc = $siteConfig->toc ? $this->pipeline->collectToc() : [];
        $related = $this->relatedIndex?->forEntry($entry->filePath) ?? [];
        $translations = $this->translationIndex?->forEntry($entry->filePath) ?? [];
        $html = $this->renderTemplate($siteConfig, $entry, $content, $permalink, $navigation, $headAssets, $toc, $related, $translations, $navigationPager);

        $html = $this->minify($siteConfig, $html);

        if ($this->cache !== null) {
            $this->cache->set($entry->filePath, $html, $cacheContext);
        }

        $finishedHtml = $this->dispatchRenderFinished($siteConfig, $entry, $permalink, $html);

        return $finishedHtml === $html ? $html : $this->minify($siteConfig, $finishedHtml);
    }

    private function minify(SiteConfig $siteConfig, string $html): string
    {
        return $siteConfig->minify ? OutputMinifier::html($html) : $html;
    }

    private function dispatchRenderFinished(SiteConfig $siteConfig, Entry $entry, string $permalink, string $html): string
    {
        if ($this->eventDispatcher === null) {
            return $html;
        }

        $event = new RenderFinishedEvent($siteConfig, $entry, $permalink, $html);
        $this->eventDispatcher->dispatch($event);

        return $event->html();
    }

    private function cacheContext(
        SiteConfig $siteConfig,
        Entry $entry,
        string $permalink,
        ?Navigation $navigation,
        ?CrossReferenceResolver $crossRefResolver,
        ?array $navigationPager,
    ): string {
        return hash('xxh128', serialize([
            'siteConfig' => $siteConfig,
            'permalink' => $permalink,
            'navigation' => $navigation,
            'navigationPager' => $navigationPager,
            'assets' => $this->assetManifest?->signature() ?? '',
            'crossReferences' => $crossRefResolver?->signature() ?? '',
            'related' => $this->relatedIndex?->signature() ?? '',
            'translations' => $this->translationIndex?->signature() ?? '',
            'lastUpdatedMtime' => $siteConfig->lastUpdated ? filemtime($entry->sourceFilePath()) : null,
        ]));
    }

    private function resolveContentDir(Entry $entry): string
    {
        $relative = substr($entry->filePath, strlen($this->contentDir) + 1);
        $dir = dirname($relative);
        return $dir === '.' ? '' : $dir;
    }

    /**
     * @param list<array{id: string, text: string, level: int}> $toc
     * @param list<\YiiPress\Content\Model\RelatedEntry> $related
     * @param list<\YiiPress\Content\Model\Translation> $translations
     * @param array{previous: array{title: string, url: string}|null, next: array{title: string, url: string}|null}|null $navigationPager
     */
    private function renderTemplate(SiteConfig $siteConfig, Entry $entry, string $content, string $permalink, ?Navigation $navigation, string $headAssets = '', array $toc = [], array $related = [], array $translations = [], ?array $navigationPager = null): string
    {
        $themeName = $entry->theme !== '' ? $entry->theme : $siteConfig->theme;
        $templateName = $entry->layout !== '' ? $entry->layout : 'entry';
        try {
            $templatePath = $this->templateResolver->resolve($templateName, $themeName);
        } catch (RuntimeException) {
            $templatePath = $this->templateResolver->resolve('entry', $themeName);
        }

        if (!isset($this->templateClosures[$templatePath])) {
            $this->templateClosures[$templatePath] = static function (array $__vars) use ($templatePath): string {
                extract($__vars, EXTR_SKIP);
                ob_start();
                require $templatePath;
                return ob_get_clean();
            };
        }

        if (!isset($this->templateContexts[$themeName])) {
            $this->templateContexts[$themeName] = new TemplateContext($this->templateResolver, $themeName, $this->assetManifest);
            $this->partialClosures[$themeName] = $this->templateContexts[$themeName]->partial(...);
        }

        $templateContext = $this->templateContexts[$themeName];
        $rootPath = UrlResolver::rootPath($permalink);
        $navigationPager = $this->relativizeNavigationPager($navigationPager, $rootPath);
        $lastUpdated = $this->lastUpdated($siteConfig, $entry);
        $editPageUrl = $siteConfig->editPageUrl === null
            ? ''
            : PageActionUrlFormatter::format($siteConfig->editPageUrl, $siteConfig, $entry, $permalink, $this->contentDir);
        $reportIssueUrl = $siteConfig->reportIssueUrl === null
            ? ''
            : PageActionUrlFormatter::format($siteConfig->reportIssueUrl, $siteConfig, $entry, $permalink, $this->contentDir);
        $metaTags = MetaTagsBuilder::forEntry($siteConfig, $entry, $permalink, $translations);
        $language = $entry->language !== ''
            ? $entry->language
            : ($siteConfig->i18n?->defaultLanguage ?? $siteConfig->defaultLanguage);
        $uiViewData = UiViewData::forSite($siteConfig, $this->templateResolver, $themeName);
        $variables = [
            'siteTitle' => $siteConfig->title,
            'entryTitle' => $entry->title,
            'content' => $content,
            'date' => $entry->date?->format($siteConfig->dateFormat) ?? '',
            'dateISO' => $entry->date?->format('Y-m-d') ?? '',
            'draft' => $entry->draft,
            'author' => $this->authorText($entry),
            'entryAuthors' => $this->entryAuthors($siteConfig, $entry, $rootPath),
            'tags' => array_values(array_filter(
                $entry->tags,
                static fn (string $tag) => !in_array(mb_strtolower($tag), $entry->inlineTags, true),
            )),
            'categories' => $entry->categories,
            'collection' => $entry->collection,
            'extra' => $entry->extra,
            'showTitle' => $entry->showTitle,
            'permalink' => $permalink,
            'nav' => $navigation,
            'headAssets' => $headAssets,
            'toc' => $toc,
            'related' => $related,
            'translations' => $translations,
            'navigationPager' => $navigationPager,
            'lastUpdated' => $lastUpdated,
            'editPageUrl' => $editPageUrl,
            'reportIssueUrl' => $reportIssueUrl,
            'language' => $language,
            'metaTags' => $metaTags,
            'partial' => $this->partialClosures[$themeName],
            'rootPath' => $rootPath,
            'assetManifest' => $this->assetManifest,
            'search' => $siteConfig->search !== null,
            'searchResults' => $siteConfig->search?->results ?? 10,
        ] + $uiViewData->toArray();

        $variables = TemplateHelpers::inject($variables);

        $html = ($this->templateClosures[$templatePath])($variables);

        return $templateContext->rewriteHtml($html, $rootPath);
    }

    private function authorText(Entry $entry): string
    {
        return implode(', ', array_map(
            fn (string $authorSlug) => $this->authors[$authorSlug]->title ?? $authorSlug,
            $entry->authors,
        ));
    }

    /**
     * @return list<array{slug: string, title: string, url: string}>
     */
    private function entryAuthors(SiteConfig $siteConfig, Entry $entry, string $rootPath): array
    {
        $entryAuthors = [];

        foreach ($entry->authors as $authorSlug) {
            $author = $this->authors[$authorSlug] ?? null;
            $entryAuthors[] = [
                'slug' => $authorSlug,
                'title' => $author instanceof Author ? $author->title : $authorSlug,
                'url' => $siteConfig->authorPages && $author instanceof Author
                    ? UrlResolver::sitePath('/authors/' . $authorSlug . '/', $rootPath)
                    : '',
            ];
        }

        return $entryAuthors;
    }

    /**
     * @return array{iso: string, text: string}|null
     */
    private function lastUpdated(SiteConfig $siteConfig, Entry $entry): ?array
    {
        if (!$siteConfig->lastUpdated) {
            return null;
        }

        $timestamp = filemtime($entry->sourceFilePath());
        if ($timestamp === false) {
            return null;
        }

        $date = (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()));

        return [
            'iso' => $date->format(DATE_ATOM),
            'text' => $date->format('n/j/y, g:i A'),
        ];
    }

    /**
     * @param array{previous: array{title: string, url: string}|null, next: array{title: string, url: string}|null}|null $navigationPager
     * @return array{previous: array{title: string, url: string}|null, next: array{title: string, url: string}|null}|null
     */
    private function relativizeNavigationPager(?array $navigationPager, string $rootPath): ?array
    {
        if ($navigationPager === null) {
            return null;
        }

        foreach (['previous', 'next'] as $key) {
            if ($navigationPager[$key] !== null && str_starts_with($navigationPager[$key]['url'], '/')) {
                $navigationPager[$key]['url'] = UrlResolver::sitePath($navigationPager[$key]['url'], $rootPath);
            }
        }

        return $navigationPager;
    }
}
