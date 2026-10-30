<?php

declare(strict_types=1);

namespace YiiPress\Build;

use FilesystemIterator;
use SplFileInfo;
use UnexpectedValueException;

use function array_values;
use function is_dir;
use function is_readable;
use function ksort;
use function preg_match;

final readonly class ProjectThemeDiscovery
{
    /**
     * @param array<string, list<string>> $managed Directory name of a theme installed by `get:theme` → its
     *        {@see Theme::$fallback}.
     * @return list<Theme>
     */
    public function discover(string $themesDir, array $managed = []): array
    {
        if (!is_dir($themesDir) || !is_readable($themesDir)) {
            return [];
        }

        $themes = [];
        try {
            $iterator = new FilesystemIterator($themesDir, FilesystemIterator::SKIP_DOTS);
            foreach ($iterator as $item) {
                /** @var SplFileInfo $item */
                if (!$item->isDir()) {
                    continue;
                }

                $name = $item->getFilename();
                if (!$this->isValidThemeName($name)) {
                    continue;
                }

                $themes[$name] = new Theme($name, $item->getPathname(), $managed[$name] ?? null);
            }
        } catch (UnexpectedValueException) {
            return [];
        }

        ksort($themes);

        return array_values($themes);
    }

    /**
     * @param array<string, list<string>> $managed Directory name of a theme installed by `get:theme` → its
     *        {@see Theme::$fallback}.
     */
    public function register(ThemeRegistry $registry, string $themesDir, array $managed = []): int
    {
        $registered = 0;
        foreach ($this->discover($themesDir, $managed) as $theme) {
            if ($registry->has($theme->name)) {
                continue;
            }

            $registry->register($theme);
            $registered++;
        }

        return $registered;
    }

    private function isValidThemeName(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/D', $name) === 1;
    }
}
