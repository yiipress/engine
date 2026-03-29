<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Navigation;
use App\Content\Model\SiteConfig;
use RuntimeException;

final readonly class NotFoundPageWriter
{
    public function __construct(private TemplateResolver $templateResolver) {}

    public function write(SiteConfig $siteConfig, string $outputDir, ?Navigation $navigation = null): void
    {
        $siteTitle = $siteConfig->title;
        $nav = $navigation;
        $partial = new TemplateContext($this->templateResolver, $siteConfig->theme)->partial(...);
        $rootPath = $this->resolveRootPath($siteConfig->baseUrl);
        $search = $siteConfig->search !== null;
        $searchResults = $siteConfig->search?->results ?? 10;

        ob_start();
        require $this->templateResolver->resolve('errors/404', $siteConfig->theme);
        $html = ob_get_clean();

        if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true) && !is_dir($outputDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $outputDir));
        }

        file_put_contents($outputDir . '/404.html', $html);
    }

    private function resolveRootPath(string $baseUrl): string
    {
        if ($baseUrl === '') {
            return '/';
        }

        $path = parse_url($baseUrl, PHP_URL_PATH) ?? '/';
        $path = rtrim((string) $path, '/') . '/';

        return $path === '' ? '/' : $path;
    }
}
