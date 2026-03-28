<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\RobotsTxtRule;
use App\Content\Model\SiteConfig;

use function rtrim;

final class RobotsTxtGenerator
{
    public function generate(SiteConfig $siteConfig): string
    {
        if (!$siteConfig->robotsTxt->generate) {
            return '';
        }

        $rules = $siteConfig->robotsTxt->rules;

        if ($rules === []) {
            $rules = [new RobotsTxtRule(userAgent: '*')];
        }

        $lines = [];

        foreach ($rules as $rule) {
            $lines[] = 'User-agent: ' . $rule->userAgent;

            foreach ($rule->allow as $path) {
                $lines[] = 'Allow: ' . $path;
            }

            foreach ($rule->disallow as $path) {
                $lines[] = 'Disallow: ' . $path;
            }

            if ($rule->crawlDelay !== null) {
                $lines[] = 'Crawl-delay: ' . $rule->crawlDelay;
            }

            $lines[] = '';
        }

        if ($siteConfig->baseUrl !== '') {
            $lines[] = 'Sitemap: ' . rtrim($siteConfig->baseUrl, '/') . '/sitemap.xml';
        }

        return implode("\n", $lines) . "\n";
    }
}
