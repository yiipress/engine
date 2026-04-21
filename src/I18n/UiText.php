<?php

declare(strict_types=1);

namespace App\I18n;

use App\Build\TemplateResolver;
use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use Locale;
use RuntimeException;

use function array_filter;
use function array_merge;
use function array_unique;
use function array_values;
use function explode;
use function file_get_contents;
use function is_array;
use function is_string;
use function mb_strtoupper;
use function mb_substr;
use function sprintf;
use function str_replace;
use function strtolower;
use function strtr;
use function ucfirst;
use function yaml_parse;

final class UiText
{
    /** @var array<string, array<string, string>> */
    private static array $catalogCache = [];

    /** @var array<string, array<int, string>> */
    private static array $monthNameCache = [];

    /** @var array<string, array<string, string>> */
    private static array $resolvedCatalogCache = [];

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

        if (isset(self::$monthNameCache[$this->language][$month])) {
            return self::$monthNameCache[$this->language][$month];
        }

        $monthDate = new DateTimeImmutable(sprintf('2000-%02d-01', $month), new DateTimeZone('UTC'));
        foreach (self::monthLocales($this->language) as $locale) {
            $formatter = IntlDateFormatter::create(
                $locale,
                IntlDateFormatter::NONE,
                IntlDateFormatter::NONE,
                'UTC',
                IntlDateFormatter::GREGORIAN,
                'LLLL',
            );
            if (!$formatter instanceof IntlDateFormatter) {
                continue;
            }

            $monthName = $formatter->format($monthDate);
            if (is_string($monthName) && $monthName !== '') {
                return self::$monthNameCache[$this->language][$month] = self::capitalizeUtf8($monthName);
            }
        }

        throw new RuntimeException('Unable to format month name for language: ' . $this->language);
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

    private static function capitalizeUtf8(string $value): string
    {
        $firstCharacter = mb_substr($value, 0, 1);
        if ($firstCharacter === '') {
            return $value;
        }

        return mb_strtoupper($firstCharacter) . mb_substr($value, 1);
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

    /**
     * @return list<string>
     */
    private static function monthLocales(string $language): array
    {
        $exactLanguage = strtolower(str_replace('_', '-', $language));

        return array_values(array_unique(array_filter([
            Locale::canonicalize(str_replace('-', '_', $exactLanguage)),
            Locale::canonicalize(str_replace('-', '_', self::normalizeLanguage($language))),
            'en',
        ])));
    }
}
