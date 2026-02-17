<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\CrossReferenceResolver;
use App\Content\Model\Entry;
use App\Content\Model\Navigation;
use App\Content\Model\SiteConfig;
use App\Processor\ContentProcessorPipeline;
use RuntimeException;

final class EntryRenderer
{
    /** @var array<string, \Closure> */
    private array $templateClosures = [];

    /** @var array<string, TemplateContext> */
    private array $templateContexts = [];

    /** @var array<string, \Closure> */
    private array $partialClosures = [];

    public function __construct(
        private readonly ContentProcessorPipeline $pipeline,
        private readonly TemplateResolver $templateResolver,
        private readonly ?BuildCache $cache = null,
        private readonly string $contentDir = '',
        private readonly array $authors = [],
    ) {}

    public function render(
        SiteConfig $siteConfig,
        Entry $entry,
        ?Navigation $navigation = null,
        ?CrossReferenceResolver $crossRefResolver = null,
    ): string {
        if ($this->cache !== null) {
            $cached = $this->cache->get($entry->sourceFilePath());
            if ($cached !== null) {
                return $cached;
            }
        }

        $body = $entry->body();
        if ($crossRefResolver !== null) {
            $body = $crossRefResolver->withCurrentDir($this->resolveContentDir($entry))->resolve($body);
        }
        $content = $this->pipeline->process($body, $entry);
        $html = $this->renderTemplate($siteConfig, $entry, $content, $navigation);

        $this->cache?->set($entry->sourceFilePath(), $html);

        return $html;
    }

    private function resolveContentDir(Entry $entry): string
    {
        $relative = substr($entry->sourceFilePath(), strlen($this->contentDir) + 1);
        $dir = dirname($relative);
        return $dir === '.' ? '' : $dir;
    }

    private function renderTemplate(SiteConfig $siteConfig, Entry $entry, string $content, ?Navigation $navigation): string
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
            $this->templateContexts[$themeName] = new TemplateContext($this->templateResolver, $themeName);
            $this->partialClosures[$themeName] = $this->templateContexts[$themeName]->partial(...);
        }

        return ($this->templateClosures[$templatePath])([
            'siteTitle' => $siteConfig->title,
            'entryTitle' => $entry->title,
            'content' => $content,
            'date' => $entry->date?->format('Y-m-d') ?? '',
            'author' => implode(', ', array_map(
                fn (string $authorSlug) => $this->authors[$authorSlug]->title ?? $authorSlug,
                $entry->authors
            )),
            'collection' => $entry->collection,
            'nav' => $navigation,
            'partial' => $this->partialClosures[$themeName],
        ]);
    }
}
