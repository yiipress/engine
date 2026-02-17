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
    public function __construct(
        private readonly ContentProcessorPipeline $pipeline,
        private readonly TemplateResolver $templateResolver,
        private readonly ?BuildCache $cache = null,
        private readonly string $contentDir = '',
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

        $siteTitle = $siteConfig->title;
        $entryTitle = $entry->title;
        $date = $entry->date?->format('Y-m-d') ?? '';
        $author = implode(', ', $entry->authors);
        $collection = $entry->collection;
        $nav = $navigation;
        $partial = (new TemplateContext($this->templateResolver, $themeName))->partial(...);

        ob_start();
        require $templatePath;
        return ob_get_clean();
    }
}
