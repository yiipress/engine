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

        if (($variables['ui'] ?? null) instanceof UiText && !isset($variables['t'])) {
            $ui = $variables['ui'];
            $variables['t'] = static fn (string $key, array $params = []): string => $ui->get($key, $params);
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
