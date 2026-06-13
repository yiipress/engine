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
            $rootPath = (string) ($variables['rootPath'] ?? '');
            $variables['url'] = static fn (string $path): string => UrlResolver::sitePath($path, $rootPath);
        }

        if (!isset($variables['themeAsset'])) {
            $themeName = (string) ($variables['themeName'] ?? '');
            $rootPath = (string) ($variables['rootPath'] ?? '');
            $assetManifest = $variables['assetManifest'] ?? null;
            $variables['themeAsset'] = static fn (string $path): string => Asset::themeUrl(
                $path,
                $themeName,
                $rootPath,
                $assetManifest instanceof AssetFingerprintManifest ? $assetManifest : null,
            );
        }

        $ui = $variables['ui'] ?? null;
        if ($ui instanceof UiText && !isset($variables['t'])) {
            $variables['t'] = static fn (string $key, array $params = []): string => $ui->get($key, $params);
        }

        if ($ui instanceof UiText && !isset($variables['languageName'])) {
            $variables['languageName'] = static fn (string $language): string => $ui->languageName($language);
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
