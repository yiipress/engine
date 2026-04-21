<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\SiteConfig;
use App\I18n\UiText;

use function array_map;
use function array_unique;
use function array_values;
use function explode;
use function str_replace;
use function strtolower;

final readonly class UiViewData
{
    /**
     * @param list<string> $languages
     * @param array<string, array<string, string>> $catalogs
     */
    private function __construct(
        public UiText $ui,
        public string $language,
        public array $languages,
        public array $catalogs,
    ) {}

    public static function forSite(SiteConfig $siteConfig, TemplateResolver $templateResolver, string $themeName = ''): self
    {
        $language = self::normalizeLanguage($siteConfig->defaultLanguage);
        $languages = array_values(array_unique(array_map(
            self::normalizeLanguage(...),
            $siteConfig->i18n?->languages ?? [$language],
        )));

        return new self(
            ui: UiText::forTheme($language, $templateResolver, $themeName, $siteConfig->defaultLanguage),
            language: $language,
            languages: $languages,
            catalogs: UiText::catalogsForTheme($languages, $templateResolver, $themeName, $siteConfig->defaultLanguage),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ui' => $this->ui,
            'uiLanguage' => $this->language,
            'uiLanguages' => $this->languages,
            'uiCatalogs' => $this->catalogs,
        ];
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
