<?php

declare(strict_types=1);

namespace App\Build;

use RuntimeException;

final class TemplateResolver
{
    public function __construct(private ThemeRegistry $themeRegistry) {}

    public function resolve(string $templateName, string $themeName = ''): string
    {
        if ($themeName !== '' && $this->themeRegistry->has($themeName)) {
            $path = $this->themeRegistry->get($themeName)->path . '/' . $templateName . '.php';
            if (is_file($path)) {
                return $path;
            }
        }

        if ($this->themeRegistry->has('default')) {
            $path = $this->themeRegistry->get('default')->path . '/' . $templateName . '.php';
            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException("Template \"$templateName\" not found.");
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
