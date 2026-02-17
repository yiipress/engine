<?php

declare(strict_types=1);

namespace App\Build;

use RuntimeException;

final class ThemeRegistry
{
    /** @var array<string, Theme> */
    private array $themes = [];

    public function register(Theme $theme): void
    {
        $this->themes[$theme->name] = $theme;
    }

    public function get(string $name): Theme
    {
        return $this->themes[$name] ?? throw new RuntimeException("Theme \"$name\" is not registered.");
    }

    public function has(string $name): bool
    {
        return isset($this->themes[$name]);
    }

    /**
     * @return list<Theme>
     */
    public function all(): array
    {
        return array_values($this->themes);
    }
}
