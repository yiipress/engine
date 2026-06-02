<?php

declare(strict_types=1);

namespace YiiPress\Hook;

use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;

final class RenderFinishedEvent implements HookEventInterface
{
    public const string NAME = 'render.finished';

    public function __construct(
        public readonly SiteConfig $siteConfig,
        public readonly Entry $entry,
        public readonly string $permalink,
        private string $html,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function html(): string
    {
        return $this->html;
    }

    public function setHtml(string $html): void
    {
        $this->html = $html;
    }
}
