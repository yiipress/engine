<?php

declare(strict_types=1);

namespace YiiPress\Build;

use Closure;

use function in_array;
use function preg_replace_callback;
use function str_contains;
use function strtolower;

/** Rewrites href/src values while preserving HTML syntax and raw text. */
final readonly class HtmlUrlRewriter
{
    private const string RAW_TAG_NAMES = '(?:script|style|textarea|title|xmp|iframe|noembed|noframes)';
    private const string TAG_CONTENT = '(?:[^"\'<>]++|"[^"]*"|\'[^\']*\')';
    private const string BEFORE_URL = '(?:[^"\'<>\s]++|"[^"]*"|\'[^\']*\'|\s++(?!(?:href|src)\s*=))*';

    // Match the first URL per tag; \K leaves its prefix outside the PHP replacement.
    // The final skip branch protects other tags' quoted values and raw-text bodies.
    private const string TAG_PATTERN = '~(?:<!--.*?(?:-->|$)|<!\[CDATA\[.*?(?:\]\]>|$))(*SKIP)(*F)|'
        . '<(?:(' . self::RAW_TAG_NAMES . ')(?=[\s/>])|[a-z][a-z0-9:-]*(?=[\s/>]))'
        . self::BEFORE_URL . '\s+(?:href|src)\s*=\s*\K'
        . '(?:(["\'])(.*?)\2|([^\s"\'`=<>]+))'
        . '(' . self::TAG_CONTENT . '*>)'
        . '(?(1)(.*?(?:</\1(?=[\s/>])(?:[^"\'>]|"[^"]*"|\'[^\']*\')*>|$)))'
        . '|<(?:(' . self::RAW_TAG_NAMES . ')(?=[\s/>])|[a-z][a-z0-9:-]*(?=[\s/>]))'
        . self::TAG_CONTENT . '*>'
        . '(?(7).*?(?:</\7(?=[\s/>])(?:[^"\'>]|"[^"]*"|\'[^\']*\')*>|$))(*SKIP)(*F)~is';

    private const string ATTRIBUTE_PATTERN = '~(\s+)([a-z_:][a-z0-9_.:-]*)(\s*=\s*)'
        . '(?:(["\'])(.*?)\4|([^\s"\'`=<>]+))~is';

    /**
     * @param Closure(string): string $rewriteUrl
     */
    public function rewrite(string $html, Closure $rewriteUrl): string
    {
        return preg_replace_callback(
            self::TAG_PATTERN,
            function (array $matches) use ($rewriteUrl): string {
                $quote = $matches[2];
                $path = $quote === '' ? $matches[4] : $matches[3];
                $tail = $matches[5];

                return $quote . $rewriteUrl($path) . $quote
                    . (str_contains($tail, '=') ? $this->rewriteTag($tail, $rewriteUrl) : $tail)
                    . ($matches[6] ?? '');
            },
            $html,
        ) ?? $html;
    }

    /**
     * Consumes whole attribute values so URL-like text inside another attribute stays unchanged.
     *
     * @param Closure(string): string $rewriteUrl
     */
    private function rewriteTag(string $tag, Closure $rewriteUrl): string
    {
        return preg_replace_callback(
            self::ATTRIBUTE_PATTERN,
            static function (array $matches) use ($rewriteUrl): string {
                if (!in_array(strtolower($matches[2]), ['href', 'src'], true)) {
                    return $matches[0];
                }

                $quote = $matches[4] ?? '';
                $path = $matches[6] ?? $matches[5] ?? '';

                return $matches[1] . $matches[2] . $matches[3] . $quote . $rewriteUrl($path) . $quote;
            },
            $tag,
        ) ?? $tag;
    }
}
