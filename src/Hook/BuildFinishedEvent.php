<?php

declare(strict_types=1);

namespace YiiPress\Hook;

use YiiPress\Content\Model\SiteConfig;

final readonly class BuildFinishedEvent implements HookEventInterface
{
    public const string NAME = 'build.finished';

    public function __construct(
        public BuildContext $context,
        public SiteConfig $siteConfig,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }
}
