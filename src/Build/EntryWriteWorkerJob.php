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

final readonly class EntryWriteWorkerJob implements WorkerJobInterface
{
    /**
     * @param list<array{entry: Entry, filePath: string, permalink: string, navigationPager?: array{previous: array{title: string, url: string}|null, next: array{title: string, url: string}|null}|null}> $tasks
     * @param array<string, Author> $authors
     */
    public function __construct(
        private SiteConfig $siteConfig,
        private array $tasks,
        private string $contentDir,
        private ?Navigation $navigation,
        private ?CrossReferenceResolver $crossRefResolver,
        private array $authors,
        private bool $noWrite,
        private ?BuildCache $cache,
        private ?AssetFingerprintManifest $assetManifest,
        private ?RelatedIndex $relatedIndex,
        private ?TranslationIndex $translationIndex,
    ) {}

    public function siteConfig(): SiteConfig
    {
        return $this->siteConfig;
    }

    /** @return list<array{entry: Entry, filePath: string, permalink: string, navigationPager?: array{previous: array{title: string, url: string}|null, next: array{title: string, url: string}|null}|null}> */
    public function tasks(): array
    {
        return $this->tasks;
    }

    public function contentDir(): string
    {
        return $this->contentDir;
    }

    public function navigation(): ?Navigation
    {
        return $this->navigation;
    }

    public function crossRefResolver(): ?CrossReferenceResolver
    {
        return $this->crossRefResolver;
    }

    /** @return array<string, Author> */
    public function authors(): array
    {
        return $this->authors;
    }

    public function noWrite(): bool
    {
        return $this->noWrite;
    }

    public function cache(): ?BuildCache
    {
        return $this->cache;
    }

    public function assetManifest(): ?AssetFingerprintManifest
    {
        return $this->assetManifest;
    }

    public function relatedIndex(): ?RelatedIndex
    {
        return $this->relatedIndex;
    }

    public function translationIndex(): ?TranslationIndex
    {
        return $this->translationIndex;
    }
}
