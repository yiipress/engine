<?php

declare(strict_types=1);

namespace YiiPress\Build;

use YiiPress\Content\Model\Collection;
use YiiPress\Content\Model\Navigation;
use YiiPress\Content\Model\SiteConfig;

/**
 * @phpstan-import-type DateArchiveTask from DateArchiveWriter
 */
final readonly class DateArchiveWorkerJob implements WorkerJobInterface
{
    /**
     * @param list<DateArchiveTask> $tasks
     */
    public function __construct(
        private array $tasks,
        private SiteConfig $siteConfig,
        private Collection $collection,
        private string $outputDir,
        private string $contentDir,
        private ?Navigation $navigation,
        private bool $noWrite,
        private ?AssetFingerprintManifest $assetManifest,
    ) {}

    /** @return list<DateArchiveTask> */
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

    public function outputDir(): string
    {
        return $this->outputDir;
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
