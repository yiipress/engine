<?php

declare(strict_types=1);

namespace YiiPress\Build;

use YiiPress\I18n\UiText;

use function htmlspecialchars;

final class TemplateHelpers
{
    /**
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    public static function inject(array $variables): array
    {
        if (!isset($variables['h'])) {
            $variables['h'] = self::escape(...);
        }

        if (!isset($variables['url'])) {
            /** @var string $rootPath */
            $rootPath = $variables['rootPath'] ?? '';
            $variables['url'] = static fn(string $path): string => UrlResolver::sitePath($path, $rootPath);
        }

        if (!isset($variables['themeAsset'])) {
            /** @var string $themeName */
            $themeName = $variables['themeName'] ?? '';
            /** @var string $rootPath */
            $rootPath = $variables['rootPath'] ?? '';
            $assetManifest = $variables['assetManifest'] ?? null;
            $variables['themeAsset'] = static fn(string $path): string => Asset::themeUrl(
                $path,
                $themeName,
                $rootPath,
                $assetManifest instanceof AssetFingerprintManifest ? $assetManifest : null,
            );
        }

        $ui = $variables['ui'] ?? null;
        if ($ui instanceof UiText && !isset($variables['t'])) {
            $translate = static function (string $key, array $params = []) use ($ui): string {
                /** @var array<string, float|int|string> $params */
                return $ui->get($key, $params);
            };
            $variables['t'] = $translate;
        }

        if ($ui instanceof UiText && !isset($variables['languageName'])) {
            $variables['languageName'] = static fn(string $language): string => $ui->languageName($language);
        }

        return $variables;
    }

    public static function escape(
        string $string,
        int $flags = ENT_QUOTES | ENT_SUBSTITUTE,
        ?string $encoding = 'UTF-8',
        bool $doubleEncode = true,
    ): string {
        return htmlspecialchars($string, $flags, $encoding, $doubleEncode);
    }
}
