<?php

declare(strict_types=1);

namespace App\Build;

use App\I18n\UiText;

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
