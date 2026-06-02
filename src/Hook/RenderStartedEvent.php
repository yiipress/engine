<?php

declare(strict_types=1);

namespace YiiPress\Hook;

use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;

final readonly class RenderStartedEvent implements HookEventInterface
{
    public const string NAME = 'render.started';

    public function __construct(
        public SiteConfig $siteConfig,
        public Entry $entry,
        public string $permalink,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }
}
