<?php

declare(strict_types=1);

namespace App\Build;

use RuntimeException;

final class TemplateResolver
{
    public function __construct(private readonly ThemeRegistry $themeRegistry) {}

    public function resolve(string $templateName, string $themeName = ''): string
    {
        if ($themeName !== '' && $this->themeRegistry->has($themeName)) {
            $path = $this->themeRegistry->get($themeName)->path . '/' . $templateName . '.php';
            if (is_file($path)) {
                return $path;
            }
        }

        foreach ($this->themeRegistry->all() as $theme) {
            if ($theme->name === $themeName) {
                continue;
            }
            $path = $theme->path . '/' . $templateName . '.php';
            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException("Template \"$templateName\" not found.");
    }

    public function resolvePartial(string $partialName, string $themeName = ''): string
    {
        return $this->resolve('partials/' . $partialName, $themeName);
    }

    /**
     * @return list<string>
     */
    public function templateDirs(): array
    {
        $dirs = [];
        foreach ($this->themeRegistry->all() as $theme) {
            $dirs[] = $theme->path;
        }
        return $dirs;
    }
}
