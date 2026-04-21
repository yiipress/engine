<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\CrossReferenceResolver;
use App\Content\I18n\TranslationIndex;
use App\Content\Model\Entry;
use App\Content\Model\Navigation;
use App\Content\Model\SiteConfig;
use App\Content\Related\RelatedIndex;
use App\I18n\UiText;
use App\Processor\ContentProcessorPipeline;
use Closure;
use RuntimeException;

use function dirname;
use function strlen;

final class EntryRenderer
{
    /** @var array<string, Closure> */
    private array $templateClosures = [];

    /** @var array<string, TemplateContext> */
    private array $templateContexts = [];

    /** @var array<string, Closure> */
    private array $partialClosures = [];

    public function __construct(
        private readonly ContentProcessorPipeline $pipeline,
        private readonly TemplateResolver $templateResolver,
        private readonly ?BuildCache $cache = null,
        private readonly string $contentDir = '',
        private readonly array $authors = [],
        private readonly ?AssetFingerprintManifest $assetManifest = null,
        private readonly ?RelatedIndex $relatedIndex = null,
        private readonly ?TranslationIndex $translationIndex = null,
    ) {}

    public function render(
        SiteConfig $siteConfig,
        Entry $entry,
        string $permalink = '',
        ?Navigation $navigation = null,
        ?CrossReferenceResolver $crossRefResolver = null,
    ): string {
        $cacheContext = ($this->relatedIndex?->signature() ?? '') . '|' . ($this->translationIndex?->signature() ?? '');
        if ($this->cache !== null) {
            $cached = $this->cache->get($entry->filePath, $cacheContext);
            if ($cached !== null) {
                return $cached;
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
        $content = $this->pipeline->process($body, $entry);
        $headAssets = $this->pipeline->collectHeadAssets($content);
        $toc = $siteConfig->toc ? $this->pipeline->collectToc() : [];
        $related = $this->relatedIndex?->forEntry($entry->filePath) ?? [];
        $translations = $this->translationIndex?->forEntry($entry->filePath) ?? [];
        $html = $this->renderTemplate($siteConfig, $entry, $content, $permalink, $navigation, $headAssets, $toc, $related, $translations);

        $this->cache?->set($entry->filePath, $html, $cacheContext);

        return $html;
    }


    private function resolveContentDir(Entry $entry): string
    {
        $relative = substr($entry->filePath, strlen($this->contentDir) + 1);
        $dir = dirname($relative);
        return $dir === '.' ? '' : $dir;
    }

    /**
     * @param list<array{id: string, text: string, level: int}> $toc
     * @param list<\App\Content\Model\RelatedEntry> $related
     * @param list<\App\Content\Model\Translation> $translations
     */
    private function renderTemplate(SiteConfig $siteConfig, Entry $entry, string $content, string $permalink, ?Navigation $navigation, string $headAssets = '', array $toc = [], array $related = [], array $translations = []): string
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
        $rootPath = RelativePathHelper::rootPath($permalink);
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
            'author' => implode(', ', array_map(
                fn (string $authorSlug) => $this->authors[$authorSlug]->title ?? $authorSlug,
                $entry->authors
            )),
            'tags' => array_values(array_filter(
                $entry->tags,
                static fn (string $tag) => !in_array(mb_strtolower($tag), $entry->inlineTags, true),
            )),
            'categories' => $entry->categories,
            'collection' => $entry->collection,
            'nav' => $navigation,
            'headAssets' => $headAssets,
            'toc' => $toc,
            'related' => $related,
            'translations' => $translations,
            'language' => $language,
            'metaTags' => $metaTags,
            'partial' => $this->partialClosures[$themeName],
            'rootPath' => $rootPath,
            'assetManifest' => $this->assetManifest,
            'search' => $siteConfig->search !== null,
            'searchResults' => $siteConfig->search?->results ?? 10,
        ] + $uiViewData->toArray();

        if (($variables['ui'] ?? null) instanceof UiText && !isset($variables['t'])) {
            $ui = $variables['ui'];
            $variables['t'] = static fn (string $key, array $params = []): string => $ui->get($key, $params);
        }

        $html = ($this->templateClosures[$templatePath])($variables);

        return $templateContext->rewriteHtml($html, $rootPath);
    }
}
