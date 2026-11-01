<?php

declare(strict_types=1);

namespace YiiPress\Build;

use Closure;

use function in_array;
use function preg_replace_callback;
use function strtolower;

/** Rewrites href/src values while preserving HTML syntax and raw text. */
final readonly class HtmlUrlRewriter
{
    private const string TAG_PATTERN = '~(?:<!--.*?(?:-->|$)|<!\[CDATA\[.*?(?:\]\]>|$))(*SKIP)(*F)|'
        . '(<(?:(script|style|textarea|title|xmp|iframe|noembed|noframes)(?=[\s/>])|[a-z][a-z0-9:-]*(?=[\s/>]))'
        . '(?:[^"\'<>]|"[^"]*"|\'[^\']*\')*>)'
        . '(?(2)(.*?(?:</\2(?=[\s/>])(?:[^"\'>]|"[^"]*"|\'[^\']*\')*>|$)))~is';

    private const string ATTRIBUTE_PATTERN = '~(\s+)([a-z_:][a-z0-9_.:-]*)(\s*=\s*)'
        . '(?:(["\'])(.*?)\4|([^\s"\'`=<>]+))~is';

    /**
     * @param Closure(string): string $rewriteUrl
     */
    public function rewrite(string $html, Closure $rewriteUrl): string
    {
        return preg_replace_callback(
            self::TAG_PATTERN,
            fn(array $matches): string => $this->rewriteTag($matches[1], $rewriteUrl) . ($matches[3] ?? ''),
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
