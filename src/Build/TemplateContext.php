<?php

declare(strict_types=1);

namespace YiiPress\Build;

use Closure;

use function ltrim;

final class TemplateContext
{
    /** @var array<string, Closure> */
    private array $closureCache = [];

    public function __construct(
        private readonly TemplateResolver $templateResolver,
        private readonly string $themeName = '',
        private readonly ?AssetFingerprintManifest $assetManifest = null,
    ) {}

    /**
     * @param array<string, mixed> $variables
     */
    public function partial(string $name, array $variables = []): string
    {
        $variables['partial'] = $this->partial(...);
        if (!isset($variables['themeName'])) {
            $variables['themeName'] = $this->themeName;
        }
        if (!isset($variables['assetManifest'])) {
            $variables['assetManifest'] = $this->assetManifest;
        }
        if (!isset($variables['themeAsset'])) {
            $rootPath = (string) ($variables['rootPath'] ?? '');
            $variables['themeAsset'] = fn (string $path): string => $this->themeAssetUrl($path, $rootPath);
        }
        $variables = TemplateHelpers::inject($variables);

        if (!isset($this->closureCache[$name])) {
            $path = $this->templateResolver->resolvePartial($name, $this->themeName);
            $this->closureCache[$name] = static function (array $__vars) use ($path): string {
                extract($__vars, EXTR_SKIP);
                ob_start();
                require $path;
                return ob_get_clean();
            };
        }

        return ($this->closureCache[$name])($variables);
    }

    public function rewriteHtml(string $html, string $rootPath = ''): string
    {
        if ($this->assetManifest === null) {
            return $html;
        }

        return new AssetUrlRewriter($this->assetManifest)->rewrite($html, $rootPath);
    }

    private function themeAssetUrl(string $path, string $rootPath): string
    {
        $ownerThemeName = $this->templateResolver->resolveResourceThemeName('assets/' . ltrim($path, '/'), $this->themeName);

        return Asset::themeUrl($path, $ownerThemeName, $rootPath, $this->assetManifest);
    }
}
