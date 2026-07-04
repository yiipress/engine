<?php

declare(strict_types=1);

namespace YiiPress\Build;

use function pathinfo;
use function preg_match;
use function preg_replace;
use function rtrim;
use function str_contains;
use function strlen;
use function strtolower;
use function substr;
use function trim;

use const PATHINFO_EXTENSION;

final class AssetMinifier
{
    private const array CSS_SPACE_BLOCKING_PREVIOUS = [
        '(' => true,
        ':' => true,
        ',' => true,
        ';' => true,
        '=' => true,
        '>' => true,
        '[' => true,
        '{' => true,
        '~' => true,
    ];
    private const array CSS_SPACE_BLOCKING_NEXT = [
        '{' => true,
        ')' => true,
        ',' => true,
        ';' => true,
        '=' => true,
        '>' => true,
        ']' => true,
        '}' => true,
        '~' => true,
    ];

    public static function supports(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension === 'css' || $extension === 'js';
    }

    public static function minify(string $path, string $content): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'css' => self::css($content),
            'js' => self::js($content),
            default => $content,
        };
    }

    private static function css(string $content): string
    {
        $content = self::stripComments($content, preserveNewLines: false, stripLineComments: false);
        $content = self::collapseCssWhitespace($content);
        $content = (string) preg_replace('/\s*([{};,>~=\[\]])\s*/', '$1', $content);
        $content = (string) preg_replace('/:\s*/', ':', $content);
        $content = (string) preg_replace('/;}/', '}', $content);

        return trim($content);
    }

    private static function js(string $content): string
    {
        $content = self::stripComments($content, preserveNewLines: true, stripLineComments: true, preserveJsRegex: true);

        return rtrim((string) preg_replace('/[ \t]+/', ' ', $content));
    }

    private static function stripComments(string $content, bool $preserveNewLines, bool $stripLineComments = true, bool $preserveJsRegex = false): string
    {
        $length = strlen($content);
        $result = '';
        $quote = '';
        $escaped = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $content[$i];
            $next = $i + 1 < $length ? $content[$i + 1] : '';

            if ($quote !== '') {
                $result .= $char;
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === $quote) {
                    $quote = '';
                }
                continue;
            }

            if ($char === '"' || $char === "'" || $char === '`') {
                $quote = $char;
                $result .= $char;
                continue;
            }

            if ($char === '/' && $next !== '/' && $next !== '*' && $preserveJsRegex && self::startsRegexLiteral($result)) {
                $result .= $char;
                $inCharacterClass = false;
                $regexEscaped = false;
                $i++;

                while ($i < $length) {
                    $regexChar = $content[$i];
                    $result .= $regexChar;

                    if ($regexEscaped) {
                        $regexEscaped = false;
                        $i++;
                        continue;
                    }

                    if ($regexChar === '\\') {
                        $regexEscaped = true;
                        $i++;
                        continue;
                    }

                    if ($regexChar === '[') {
                        $inCharacterClass = true;
                        $i++;
                        continue;
                    }

                    if ($regexChar === ']') {
                        $inCharacterClass = false;
                        $i++;
                        continue;
                    }

                    if ($regexChar === '/' && !$inCharacterClass) {
                        break;
                    }

                    $i++;
                }

                while ($i + 1 < $length && preg_match('/[A-Za-z]/', $content[$i + 1]) === 1) {
                    $i++;
                    $result .= $content[$i];
                }

                continue;
            }

            if ($char === '/' && $next === '*') {
                $comment = '';
                $i += 2;
                while ($i < $length) {
                    if ($content[$i] === '*' && $i + 1 < $length && $content[$i + 1] === '/') {
                        $i++;
                        break;
                    }
                    if ($preserveNewLines && ($content[$i] === "\n" || $content[$i] === "\r")) {
                        $comment .= $content[$i];
                    }
                    $i++;
                }
                $result .= $preserveNewLines && str_contains($comment, "\n") ? $comment : ' ';
                continue;
            }

            if ($stripLineComments && $char === '/' && $next === '/') {
                $i += 2;
                while ($i < $length && $content[$i] !== "\n") {
                    $i++;
                }
                if ($i < $length) {
                    $result .= "\n";
                }
                continue;
            }

            $result .= $char;
        }

        return $result;
    }

    private static function startsRegexLiteral(string $code): bool
    {
        $code = rtrim($code);
        if ($code === '') {
            return true;
        }

        $last = substr($code, -1);
        if (match ($last) {
            '(', '[', '{', '=', ',', ':', ';', '!', '&', '|', '?', '+', '-', '*', '~', '^', '<', '>', '%' => true,
            default => false,
        }) {
            return true;
        }

        return preg_match('/(?:^|[^A-Za-z0-9_$])(?:return|throw|case|delete|typeof|void|new|yield)$/', $code) === 1;
    }

    private static function collapseCssWhitespace(string $content): string
    {
        $length = strlen($content);
        $result = '';
        $quote = '';
        $escaped = false;
        $pendingSpace = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $content[$i];

            if ($quote !== '') {
                if ($pendingSpace) {
                    $result .= ' ';
                    $pendingSpace = false;
                }
                $result .= $char;
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === $quote) {
                    $quote = '';
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                if ($pendingSpace) {
                    $result .= ' ';
                    $pendingSpace = false;
                }
                $quote = $char;
                $result .= $char;
                continue;
            }

            if ($char === ' ' || $char === "\t" || $char === "\n" || $char === "\r") {
                $pendingSpace = $result !== '';
                continue;
            }

            if ($pendingSpace && self::needsCssSpace($result, $char)) {
                $result .= ' ';
            }
            $pendingSpace = false;
            $result .= $char;
        }

        return $result;
    }

    private static function needsCssSpace(string $previousContent, string $next): bool
    {
        $previous = substr($previousContent, -1);

        return $previous !== ''
            && !isset(self::CSS_SPACE_BLOCKING_PREVIOUS[$previous])
            && !isset(self::CSS_SPACE_BLOCKING_NEXT[$next]);
    }
}
