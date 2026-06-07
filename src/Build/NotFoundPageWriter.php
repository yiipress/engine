<?php

declare(strict_types=1);

namespace YiiPress\Build;

use YiiPress\Content\Model\Navigation;
use YiiPress\Content\Model\SiteConfig;
use RuntimeException;

final readonly class NotFoundPageWriter
{
    public function __construct(
        private TemplateResolver $templateResolver,
        private ?AssetFingerprintManifest $assetManifest = null,
    ) {}

    public function write(SiteConfig $siteConfig, string $outputDir, ?Navigation $navigation = null, bool $noWrite = false): void
    {
        $rootPath = './';
        $uiViewData = UiViewData::forSite($siteConfig, $this->templateResolver, $siteConfig->theme);
        $renderer = new PageTemplateRenderer($this->templateResolver, $siteConfig->theme, $this->assetManifest);
        $html = $renderer->render('errors/404', [
            'siteTitle' => $siteConfig->title,
            'nav' => $navigation,
            'rootPath' => $rootPath,
            'search' => $siteConfig->search !== null,
            'searchResults' => $siteConfig->search?->results ?? 10,
            'language' => $siteConfig->defaultLanguage,
        ] + $uiViewData->toArray(), $rootPath);

        if ($noWrite) {
            return;
        }

        if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true) && !is_dir($outputDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $outputDir));
        }

        file_put_contents($outputDir . '/404.html', $html);
    }
}
