<?php

declare(strict_types=1);

namespace YiiPress\Processor;

use YiiPress\Content\Model\SiteConfig;

interface SiteConfigAwareProcessorInterface
{
    public function applySiteConfig(SiteConfig $siteConfig): void;
}
