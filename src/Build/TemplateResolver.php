<?php

declare(strict_types=1);

namespace YiiPress\Build;

use RuntimeException;

use function array_key_exists;
use function implode;
use function is_file;
use function sprintf;

final class TemplateResolver
{
    /** @var array<string, string> */
    private array $cache = [];

    /** @var array<string, string|null> */
    private array $resourceCache = [];

    public function __construct(private readonly ThemeRegistry $themeRegistry) {}

    public function resolve(string $templateName, string $themeName = ''): string
    {
        $key = $themeName . "\0" . $templateName;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $tried = [];
        foreach ($this->candidates($themeName) as $theme) {
            $path = $theme->path . '/' . $templateName . '.php';
            if (is_file($path)) {
                return $this->cache[$key] = $path;
            }
            $tried[] = $theme->name;
        }

        throw new RuntimeException(sprintf(
            'Template "%s" not found in themes: %s.',
            $templateName,
            implode(', ', $tried),
        ));
    }

    public function resolvePartial(string $partialName, string $themeName = ''): string
    {
        return $this->resolve('partials/' . $partialName, $themeName);
    }

    public function resolveResource(string $resourcePath, string $themeName = ''): ?string
    {
        $key = $themeName . "\0" . $resourcePath;
        if (array_key_exists($key, $this->resourceCache)) {
            return $this->resourceCache[$key];
        }

        foreach ($this->candidates($themeName) as $theme) {
            $path = $theme->path . '/' . $resourcePath;
            if (is_file($path)) {
                return $this->resourceCache[$key] = $path;
            }
        }

        return $this->resourceCache[$key] = null;
    }

    public function resolveResourceThemeName(string $resourcePath, string $themeName = ''): string
    {
        // Called for every asset reference of every page: skip building the fallback list when the theme has the file.
        if (
            $themeName !== ''
            && $this->themeRegistry->has($themeName)
            && is_file($this->themeRegistry->get($themeName)->path . '/' . $resourcePath)
        ) {
            return $themeName;
        }

        foreach ($this->candidates($themeName) as $theme) {
            if (is_file($theme->path . '/' . $resourcePath)) {
                return $theme->name;
            }
        }

        return $themeName !== '' ? $themeName : $this->defaultThemeName();
    }

    public function defaultThemeName(): string
    {
        foreach ($this->themeRegistry->all() as $theme) {
            if (!$theme->managed()) {
                return $theme->name;
            }
        }

        return '';
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

    /**
     * Themes to look in for a file of the given theme, in order: the theme itself, then its declared fallback for a
     * managed theme, or every other registered theme that is not managed.
     *
     * @return list<Theme>
     */
    private function candidates(string $themeName): array
    {
        $requested = $themeName !== '' && $this->themeRegistry->has($themeName)
            ? $this->themeRegistry->get($themeName)
            : null;
        if ($requested?->fallback !== null) {
            $themes = [$requested];
            foreach ($requested->fallback as $name) {
                if ($name !== $themeName && $this->themeRegistry->has($name)) {
                    $themes[] = $this->themeRegistry->get($name);
                }
            }

            return $themes;
        }

        $themes = $requested === null ? [] : [$requested];
        foreach ($this->themeRegistry->all() as $theme) {
            if ($theme->name !== $themeName && !$theme->managed()) {
                $themes[] = $theme;
            }
        }

        return $themes;
    }
}
