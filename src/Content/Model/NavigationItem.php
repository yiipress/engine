<?php

declare(strict_types=1);

namespace App\Content\Model;

final readonly class NavigationItem
{
    /**
     * @param list<NavigationItem> $children
     * @param array<string, string> $titles
     */
    public function __construct(
        public string $title,
        public string $url,
        public array $children,
        public array $titles = [],
    ) {}

    public function resolveTitle(string $language, string $defaultLanguage = 'en'): string
    {
        if ($this->titles === []) {
            return $this->title;
        }

        foreach (array_unique([
            self::normalizeLanguage($language),
            self::normalizeLanguage($defaultLanguage),
            'en',
        ]) as $candidate) {
            if (isset($this->titles[$candidate]) && $this->titles[$candidate] !== '') {
                return $this->titles[$candidate];
            }
        }

        return $this->title;
    }

    private static function normalizeLanguage(string $language): string
    {
        $normalized = strtolower(str_replace('_', '-', $language));
        if ($normalized === '') {
            return 'en';
        }

        return explode('-', $normalized)[0];
    }
}
