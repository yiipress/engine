<?php

declare(strict_types=1);

namespace App\Build;

final class TemplateContext
{
    public function __construct(
        private readonly TemplateResolver $templateResolver,
        private readonly string $themeName = '',
    ) {}

    /**
     * @param array<string, mixed> $variables
     */
    public function partial(string $name, array $variables = []): string
    {
        $__path = $this->templateResolver->resolvePartial($name, $this->themeName);
        $variables['partial'] = $this->partial(...);

        return (static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            require $__file;
            return ob_get_clean();
        })($__path, $variables);
    }
}
