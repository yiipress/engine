<?php

declare(strict_types=1);

namespace YiiPress\Build;

use function preg_replace;
use function preg_split;
use function preg_match_all;
use function strlen;
use function str_starts_with;
use function substr;
use function trim;

final class OutputMinifier
{
    private const string PROTECTED_ELEMENT_PATTERN = '~<(?<tag>pre|textarea|script|style)\b(?:[^>"\']+|"[^"]*"|\'[^\']*\')*>.*?</\k<tag>>~is';
    private const string TAG_PATTERN = '~(<(?:[^>"\']+|"[^"]*"|\'[^\']*\')*>)~';

    /**
     * Minifies generated HTML while preserving whitespace-sensitive element bodies.
     */
    public static function html(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $protectedParts = self::splitProtectedParts($html);
        if ($protectedParts === null) {
            return $html;
        }

        $minified = '';
        $previousPartProtected = false;
        foreach ($protectedParts as $part) {
            if ($part['html'] === '') {
                continue;
            }

            if ($part['protected']) {
                $minified = preg_replace('~(?<=>)\s+$~', '', $minified) ?? $minified;
                $minified .= $part['html'];
                $previousPartProtected = true;
                continue;
            }

            $fragment = self::minifyHtmlFragment($part['html']);
            if ($previousPartProtected) {
                $fragment = preg_replace('~^\s+(?=<)~', '', $fragment) ?? $fragment;
            }

            $minified .= $fragment;
            $previousPartProtected = false;
        }

        return trim($minified);
    }

    /**
     * @return list<array{html: string, protected: bool}>|null
     */
    private static function splitProtectedParts(string $html): ?array
    {
        $matched = preg_match_all(self::PROTECTED_ELEMENT_PATTERN, $html, $matches, PREG_OFFSET_CAPTURE);
        if ($matched === false) {
            return null;
        }

        if ($matched === 0) {
            return [['html' => $html, 'protected' => false]];
        }

        $parts = [];
        $offset = 0;
        foreach ($matches[0] as [$match, $position]) {
            if ($position > $offset) {
                $parts[] = [
                    'html' => substr($html, $offset, $position - $offset),
                    'protected' => false,
                ];
            }

            $parts[] = [
                'html' => $match,
                'protected' => true,
            ];
            $offset = $position + strlen($match);
        }

        if ($offset < strlen($html)) {
            $parts[] = [
                'html' => substr($html, $offset),
                'protected' => false,
            ];
        }

        return $parts;
    }

    private static function minifyHtmlFragment(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $tokens = preg_split(self::TAG_PATTERN, $html, -1, PREG_SPLIT_DELIM_CAPTURE);
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
