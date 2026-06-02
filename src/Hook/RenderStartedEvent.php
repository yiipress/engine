<?php

declare(strict_types=1);

namespace YiiPress\Hook;

use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;

final readonly class RenderStartedEvent
{
    public function __construct(
        public SiteConfig $siteConfig,
        public Entry $entry,
        public string $permalink,
    ) {}
}
