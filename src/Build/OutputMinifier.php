<?php

declare(strict_types=1);

namespace YiiPress\Build;

use function preg_replace;
use function preg_split;
use function preg_match;
use function str_starts_with;
use function trim;

final class OutputMinifier
{
    /**
     * Minifies generated HTML while preserving whitespace-sensitive element bodies.
     */
    public static function html(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $protectedParts = preg_split(
            '~(<(?:pre|textarea|script|style)\b[^>]*>.*?</(?:pre|textarea|script|style)>)~is',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE,
        );
        if ($protectedParts === false) {
            return $html;
        }

        $minified = '';
        $previousPartProtected = false;
        foreach ($protectedParts as $part) {
            if ($part === '') {
                continue;
            }

            if (preg_match('~^<(?:pre|textarea|script|style)\b~i', $part) === 1) {
                $minified = preg_replace('~(?<=>)\s+$~', '', $minified) ?? $minified;
                $minified .= $part;
                $previousPartProtected = true;
                continue;
            }

            $fragment = self::minifyHtmlFragment($part);
            if ($previousPartProtected) {
                $fragment = preg_replace('~^\s+(?=<)~', '', $fragment) ?? $fragment;
            }

            $minified .= $fragment;
            $previousPartProtected = false;
        }

        return trim($minified);
    }

    private static function minifyHtmlFragment(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $tokens = preg_split('~(<[^>]+>)~', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($tokens === false) {
            return $html;
        }

        $minified = '';
        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            if (str_starts_with($token, '<')) {
                $minified .= $token;
                continue;
            }

            $minified .= preg_replace('~[ \t\r\n\f]+~', ' ', $token) ?? $token;
        }

        return preg_replace('~>\s+<~', '><', $minified) ?? $minified;
    }
}
