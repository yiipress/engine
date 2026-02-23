<?php

declare(strict_types=1);

namespace App\Build;

use Closure;

final class TemplateContext
{
    /** @var array<string, Closure> */
    private array $closureCache = [];

    public function __construct(
        private readonly TemplateResolver $templateResolver,
        private readonly string $themeName = '',
    ) {}

    /**
     * @param array<string, mixed> $variables
     */
    public function partial(string $name, array $variables = []): string
    {
        $variables['partial'] = $this->partial(...);

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
}
