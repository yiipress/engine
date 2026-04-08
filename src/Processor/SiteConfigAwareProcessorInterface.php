<?php

declare(strict_types=1);

namespace App\Processor;

use App\Content\Model\SiteConfig;

interface SiteConfigAwareProcessorInterface
{
    public function applySiteConfig(SiteConfig $siteConfig): void;
}
