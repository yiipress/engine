<?php

declare(strict_types=1);

namespace YiiPress\Build;

use YiiPress\Content\Model\Collection;
use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\Navigation;
use YiiPress\Content\Model\SiteConfig;

final readonly class CollectionListingWorkerJob implements WorkerJobInterface
{
    /**
     * @param list<array{entries: list<Entry>, pagination: array{currentPage: int, totalPages: int, previousUrl: string, nextUrl: string}, rootPath: string, permalink: string, dir: string}> $tasks
     */
    public function __construct(
        private array $tasks,
        private SiteConfig $siteConfig,
        private Collection $collection,
        private string $contentDir,
        private ?Navigation $navigation,
        private bool $noWrite,
        private ?AssetFingerprintManifest $assetManifest,
    ) {}

    /** @return list<array{entries: list<Entry>, pagination: array{currentPage: int, totalPages: int, previousUrl: string, nextUrl: string}, rootPath: string, permalink: string, dir: string}> */
    public function tasks(): array
    {
        return $this->tasks;
    }

    public function siteConfig(): SiteConfig
    {
        return $this->siteConfig;
    }

    public function collection(): Collection
    {
        return $this->collection;
    }

    public function contentDir(): string
    {
        return $this->contentDir;
    }

    public function navigation(): ?Navigation
    {
        return $this->navigation;
    }

    public function noWrite(): bool
    {
        return $this->noWrite;
    }

    public function assetManifest(): ?AssetFingerprintManifest
    {
        return $this->assetManifest;
    }
}
