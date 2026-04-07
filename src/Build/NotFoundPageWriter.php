<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Navigation;
use App\Content\Model\SiteConfig;
use RuntimeException;

final readonly class NotFoundPageWriter
{
    public function __construct(
        private TemplateResolver $templateResolver,
        private ?AssetFingerprintManifest $assetManifest = null,
    ) {}

    public function write(SiteConfig $siteConfig, string $outputDir, ?Navigation $navigation = null): void
    {
        $siteTitle = $siteConfig->title;
        $nav = $navigation;
        $templateContext = new TemplateContext($this->templateResolver, $siteConfig->theme, $this->assetManifest);
        $partial = $templateContext->partial(...);
        $rootPath = './';
        $assetManifest = $this->assetManifest;
        $search = $siteConfig->search !== null;
        $searchResults = $siteConfig->search?->results ?? 10;

        ob_start();
        require $this->templateResolver->resolve('errors/404', $siteConfig->theme);
        $html = $templateContext->rewriteHtml((string) ob_get_clean(), $rootPath);

        if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true) && !is_dir($outputDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $outputDir));
        }

        file_put_contents($outputDir . '/404.html', $html);
    }
}
