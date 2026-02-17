<?php

declare(strict_types=1);

namespace App\Build;

final class TemplateResolver
{
    private const string DEFAULT_TEMPLATE_DIR = __DIR__ . '/../../templates';

    /**
     * @param list<string> $templateDirs Directories to search, in priority order.
     */
    public function __construct(private array $templateDirs = []) {}

    public function resolve(string $templateName): string
    {
        foreach ($this->templateDirs as $dir) {
            $path = $dir . '/' . $templateName . '.php';
            if (is_file($path)) {
                return $path;
            }
        }

        $defaultPath = self::DEFAULT_TEMPLATE_DIR . '/' . $templateName . '.php';
        if (is_file($defaultPath)) {
            return $defaultPath;
        }

        throw new \RuntimeException("Template \"$templateName\" not found.");
    }
}
