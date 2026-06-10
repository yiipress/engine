<?php

declare(strict_types=1);

namespace YiiPress\Build;

use function preg_replace;
use function preg_split;
use function preg_match_all;
use function strlen;
use function stripos;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

final class OutputMinifier
{
    private const string PROTECTED_START_TAG_PATTERN = '~<(?<tag>pre|textarea|script|style)\b(?:[^>"\']+|"[^"]*"|\'[^\']*\')*>~i';
    private const string TAG_PATTERN = '~(<(?:[^>"\']+|"[^"]*"|\'[^\']*\')*>)~';

    /**
     * Minifies generated HTML while preserving whitespace-sensitive element bodies.
     */
    public static function html(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $matched = preg_match_all(self::PROTECTED_START_TAG_PATTERN, $html, $matches, PREG_OFFSET_CAPTURE);
        if ($matched === false) {
            return $html;
        }

        if ($matched === 0) {
            return trim(self::minifyHtmlFragment($html));
        }

        $minified = '';
        $previousPartProtected = false;
        foreach (self::splitProtectedParts($html, $matches) as $part) {
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
     * @param array<array-key, list<array{0: string, 1: int}>> $matches
     * @return list<array{html: string, protected: bool}>
     */
    private static function splitProtectedParts(string $html, array $matches): array
    {
        $parts = [];
        $offset = 0;
        $length = strlen($html);

        foreach ($matches[0] as $index => $startTag) {
            /** @var array{0: string, 1: int} $startTag */
            /** @var array{0: string, 1: int} $tagMatch */
            $tagMatch = $matches['tag'][$index];
            $position = $startTag[1];
            if ($position < $offset) {
                continue;
            }

            $contentOffset = $position + strlen($startTag[0]);
            $closingTag = '</' . strtolower($tagMatch[0]) . '>';
            $closingPosition = stripos($html, $closingTag, $contentOffset);

            if ($closingPosition === false) {
                continue;
            }

            if ($position > $offset) {
                $parts[] = [
                    'html' => substr($html, $offset, $position - $offset),
                    'protected' => false,
                ];
            }

            $endOffset = $closingPosition + strlen($closingTag);
            $parts[] = [
                'html' => substr($html, $position, $endOffset - $position),
                'protected' => true,
            ];

            $offset = $endOffset;
        }

        if ($offset < $length) {
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
