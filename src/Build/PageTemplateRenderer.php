<?php

declare(strict_types=1);

namespace App\Build;

use Closure;

final class PageTemplateRenderer
{
    /** @var array<string, Closure> */
    private array $templateClosures = [];

    /** @var array<string, TemplateContext> */
    private array $templateContexts = [];

    /** @var array<string, Closure> */
    private array $partialClosures = [];

    public function __construct(
        private readonly TemplateResolver $templateResolver,
        private readonly string $themeName,
        private readonly ?AssetFingerprintManifest $assetManifest = null,
    ) {}

    /**
     * @param array<string, mixed> $variables
     */
    public function render(string $templateName, array $variables, string $rootPath): string
    {
        $templatePath = $this->templateResolver->resolve($templateName, $this->themeName);

        if (!isset($this->templateClosures[$templatePath])) {
            $this->templateClosures[$templatePath] = static function (array $__vars) use ($templatePath): string {
                extract($__vars, EXTR_SKIP);
                ob_start();
                require $templatePath;
                return (string) ob_get_clean();
            };
        }

        if (!isset($this->templateContexts[$this->themeName])) {
            $this->templateContexts[$this->themeName] = new TemplateContext(
                $this->templateResolver,
                $this->themeName,
                $this->assetManifest,
            );
            $this->partialClosures[$this->themeName] = $this->templateContexts[$this->themeName]->partial(...);
        }

        $variables['partial'] = $this->partialClosures[$this->themeName];
        $variables['assetManifest'] = $this->assetManifest;
        $variables = TemplateHelpers::inject($variables);

        $html = ($this->templateClosures[$templatePath])($variables);

        return $this->templateContexts[$this->themeName]->rewriteHtml($html, $rootPath);
    }
}
