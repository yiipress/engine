<?php

declare(strict_types=1);

namespace App\Content\Model;

final readonly class Navigation
{
    /**
     * @param array<string, list<NavigationItem>> $menus
     */
    public function __construct(
        public array $menus,
    ) {}

    /**
     * @return list<NavigationItem>
     */
    public function menu(string $name): array
    {
        return $this->menus[$name] ?? [];
    }

    /**
     * @return list<string>
     */
    public function menuNames(): array
    {
        return array_keys($this->menus);
    }
}
