<?php

declare(strict_types=1);

namespace YiiPress\Extension\Install;

use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Platform;
use YiiPress\Extension\Release\GitHubRepository;

/**
 * What to install and where.
 */
final readonly class InstallRequest
{
    /**
     * @param string $projectRoot Directory holding `yiipress.lock`, `.yiipress/` and the extension directories.
     * @param string|null $version Requested `X.Y.Z` without `v`; `null` picks the highest compatible release.
     */
    public function __construct(
        public string $projectRoot,
        public GitHubRepository $repository,
        public ExtensionType $type,
        public ?string $version,
        public EngineVersion $engine,
        public Platform $platform,
    ) {}
}
