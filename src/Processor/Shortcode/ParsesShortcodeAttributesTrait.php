<?php

declare(strict_types=1);

namespace App\Processor\Shortcode;

/**
 * Trait for parsing shortcode attributes from a string.
 *
 * Supports:
 *   key="value" - double quotes
 *   key='value' - single quotes
 *   key=value   - no quotes (no spaces in value)
 */
trait ParsesShortcodeAttributesTrait
{
    /**
     * Parse shortcode attributes from a string.
     *
     * @return array<string, string>
     */
    private function parseAttributes(string $attributeString): array
    {
        $attributes = [];

        if (preg_match_all(
            '/(\w+)=(?:"([^"]*)"|\'([^\']*)\'|([^\s\]]+))/',
            $attributeString,
            $matches,
            PREG_SET_ORDER,
        )) {
            foreach ($matches as $match) {
                $key = $match[1];
                $value = $match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : $match[4]);
                $attributes[$key] = $value;
            }
        }

        return $attributes;
    }
}
