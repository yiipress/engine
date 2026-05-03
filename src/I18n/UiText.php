<?php

declare(strict_types=1);

namespace App\I18n;

use App\Build\TemplateResolver;
use RuntimeException;

use function array_filter;
use function array_merge;
use function array_unique;
use function array_values;
use function explode;
use function file_get_contents;
use function is_array;
use function is_string;
use function sprintf;
use function str_replace;
use function strtolower;
use function strtoupper;
use function strtr;
use function ucfirst;
use function yaml_parse;

final class UiText
{
    /** @var array<string, array<int, string>> */
    private const array BUILTIN_MONTH_NAMES = [
        'en' => [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ],
        'ru' => [
            1 => 'Январь',
            2 => 'Февраль',
            3 => 'Март',
            4 => 'Апрель',
            5 => 'Май',
            6 => 'Июнь',
            7 => 'Июль',
            8 => 'Август',
            9 => 'Сентябрь',
            10 => 'Октябрь',
            11 => 'Ноябрь',
            12 => 'Декабрь',
        ],
    ];

    /** @var array<string, string> */
    private const array BUILTIN_LANGUAGE_NAMES = [
        'en' => 'English',
        'ru' => 'Русский',
    ];

    /** @var array<string, array<string, string>> */
    private static array $catalogCache = [];

    /** @var array<string, array<string, string>> */
    private static array $resolvedCatalogCache = [];

    /** @var array<int, string> */
    private array $monthNameCache = [];

    /**
     * @param array<string, array<string, string>> $catalogs
     */
    private function __construct(
        private string $language,
        private string $defaultLanguage = 'en',
        private array $catalogs = [],
    ) {}

    public static function for(string $language, string $defaultLanguage = 'en'): self
    {
        $normalizedLanguage = self::normalizeLanguage($language);
        $normalizedDefaultLanguage = self::normalizeLanguage($defaultLanguage);

        return new self($normalizedLanguage, $normalizedDefaultLanguage);
    }

    public static function forTheme(
        string $language,
        TemplateResolver $templateResolver,
        string $themeName = '',
        string $defaultLanguage = 'en',
    ): self
    {
        $normalizedLanguage = self::normalizeLanguage($language);
        $normalizedDefaultLanguage = self::normalizeLanguage($defaultLanguage);
        $catalogs = [
            'en' => self::resolveCatalog('en', $templateResolver, $themeName, 'en'),
            $normalizedDefaultLanguage => self::resolveCatalog(
                $defaultLanguage,
                $templateResolver,
                $themeName,
                $normalizedDefaultLanguage,
            ),
            $normalizedLanguage => self::resolveCatalog(
                $language,
                $templateResolver,
                $themeName,
                $normalizedDefaultLanguage,
            ),
        ];

        return new self($normalizedLanguage, $normalizedDefaultLanguage, $catalogs);
    }

    /**
     * @param list<string> $languages
     * @return array<string, array<string, string>>
     */
    public static function catalogsForTheme(
        array $languages,
        TemplateResolver $templateResolver,
        string $themeName = '',
        string $defaultLanguage = 'en',
    ): array {
        $catalogs = [];
        $normalizedDefaultLanguage = self::normalizeLanguage($defaultLanguage);

        foreach (array_values(array_unique(array_map(self::normalizeLanguage(...), $languages))) as $language) {
            $catalogs[$language] = self::resolveCatalog(
                $language,
                $templateResolver,
                $themeName,
                $normalizedDefaultLanguage,
            );
        }

        return $catalogs;
    }

    /**
     * @param array<string, string|int|float> $params
     */
    public function get(string $key, array $params = []): string
    {
        $message = $this->resolveMessage($key) ?? $key;

        if ($params === []) {
            return $message;
        }

        $replacements = [];
        foreach ($params as $name => $value) {
            $replacements['{' . $name . '}'] = (string) $value;
        }

        return strtr($message, $replacements);
    }

    public function monthName(int $month): string
    {
        if ($month < 1 || $month > 12) {
            throw new RuntimeException('Month must be between 1 and 12.');
        }

        if (isset($this->monthNameCache[$month])) {
            return $this->monthNameCache[$month];
        }

        $catalogName = $this->resolveMessage(sprintf('month.%02d', $month));
        if ($catalogName !== null && $catalogName !== '') {
            return $this->monthNameCache[$month] = $catalogName;
        }

        foreach (array_unique([$this->language, $this->defaultLanguage, 'en']) as $language) {
            if (isset(self::BUILTIN_MONTH_NAMES[$language][$month])) {
                return $this->monthNameCache[$month] = self::BUILTIN_MONTH_NAMES[$language][$month];
            }
        }

        throw new RuntimeException('Unable to format month name for language: ' . $this->language);
    }

    public function languageName(string $language): string
    {
        $normalizedLanguage = self::normalizeLanguage($language);
        $catalogName = $this->resolveMessage('language.' . $normalizedLanguage);
        if ($catalogName !== null && $catalogName !== '') {
            return $catalogName;
        }

        return self::BUILTIN_LANGUAGE_NAMES[$normalizedLanguage] ?? strtoupper($normalizedLanguage);
    }

    public function taxonomyLabel(string $taxonomyName): string
    {
        $key = 'taxonomy.' . strtolower($taxonomyName);
        $label = $this->resolveMessage($key) ?? '';

        return $label !== '' ? $label : ucfirst($taxonomyName);
    }

    private function resolveMessage(string $key): ?string
    {
        foreach (array_unique([$this->language, $this->defaultLanguage, 'en']) as $language) {
            if (isset($this->catalogs[$language][$key])) {
                return $this->catalogs[$language][$key];
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private static function loadCatalog(string $path): array
    {
        if (isset(self::$catalogCache[$path])) {
            return self::$catalogCache[$path];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Cannot read UI translation file: $path");
        }

        $data = yaml_parse($content);
        if (!is_array($data)) {
            throw new RuntimeException("Invalid YAML in UI translation file: $path");
        }

        $catalog = [];
        foreach ($data as $key => $value) {
            $catalog[(string) $key] = (string) $value;
        }

        return self::$catalogCache[$path] = $catalog;
    }

    private static function normalizeLanguage(string $language): string
    {
        $normalized = strtolower(str_replace('_', '-', $language));
        if ($normalized === '') {
            return 'en';
        }

        $parts = explode('-', $normalized);

        return $parts[0];
    }

    /**
     * @return list<string>
     */
    private static function resolveLanguagePaths(
        string $language,
        string $normalizedLanguage,
        TemplateResolver $templateResolver,
        string $themeName,
    ): array {
        $exactLanguage = strtolower(str_replace('_', '-', $language));

        return array_values(array_unique(array_filter([
            $templateResolver->resolveResource('translation/' . $exactLanguage . '.yaml', $themeName),
            $templateResolver->resolveResource('translation/' . $normalizedLanguage . '.yaml', $themeName),
        ])));
    }

    /**
     * @return array<string, string>
     */
    private static function resolveCatalog(
        string $language,
        TemplateResolver $templateResolver,
        string $themeName,
        string $defaultLanguage,
    ): array {
        $normalizedLanguage = self::normalizeLanguage($language);
        $normalizedDefaultLanguage = self::normalizeLanguage($defaultLanguage);
        $cacheKey = $themeName . "\0" . $normalizedDefaultLanguage . "\0" . $normalizedLanguage;
        if (isset(self::$resolvedCatalogCache[$cacheKey])) {
            return self::$resolvedCatalogCache[$cacheKey];
        }

        $catalog = [];
        $englishPath = $templateResolver->resolveResource('translation/en.yaml', $themeName);
        if ($englishPath !== null) {
            $catalog = self::loadCatalog($englishPath);
        }

        if ($normalizedDefaultLanguage !== 'en') {
            foreach (self::resolveLanguagePaths($defaultLanguage, $normalizedDefaultLanguage, $templateResolver, $themeName) as $path) {
                $catalog = array_merge($catalog, self::loadCatalog($path));
            }
        }

        if ($normalizedLanguage !== $normalizedDefaultLanguage) {
            foreach (self::resolveLanguagePaths($language, $normalizedLanguage, $templateResolver, $themeName) as $path) {
                $catalog = array_merge($catalog, self::loadCatalog($path));
            }
        }

        return self::$resolvedCatalogCache[$cacheKey] = $catalog;
    }

}
