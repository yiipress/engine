<?php

declare(strict_types=1);

namespace YiiPress\Hook;

use YiiPress\Content\Model\SiteConfig;

final readonly class BuildFinishedEvent
{
    public function __construct(
        public BuildContext $context,
        public SiteConfig $siteConfig,
    ) {}
}
