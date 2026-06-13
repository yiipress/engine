<?php

declare(strict_types=1);

namespace YiiPress\Processor\Shortcode;

use Throwable;
use YiiPress\Content\Model\Entry;
use YiiPress\Processor\ContentProcessorInterface;

use function dirname;
use function is_dir;
use function is_file;
use function ob_get_clean;
use function ob_start;
use function preg_replace_callback;
use function str_contains;

/**
 * Expands site-level shortcodes from content/shortcodes/*.php before markdown processing.
 */
final class ProjectShortcodeProcessor implements ContentProcessorInterface
{
    use ParsesShortcodeAttributesTrait;

    private const string BLOCK_PATTERN = '/\{\{<\s*([A-Za-z][A-Za-z0-9_-]*)\b([^>]*)>\}\}(.*?)\{\{<\s*\/\1\s*>\}\}/s';
    private const string INLINE_PATTERN = '/\{\{<\s*([A-Za-z][A-Za-z0-9_-]*)\b([^>]*)\/?\s*>\}\}/';

    public function process(string $content, Entry $entry): string
    {
        if (!str_contains($content, '{{<')) {
            return $content;
        }

        $shortcodeDir = $this->shortcodeDirectory($entry);
        if ($shortcodeDir === '') {
            return $content;
        }

        $content = (string) preg_replace_callback(
            self::BLOCK_PATTERN,
            fn (array $matches): string => $this->renderOrOriginal(
                $shortcodeDir,
                $matches[1],
                $matches[2],
                $entry,
                $matches[3],
                $matches[0],
            ),
            $content,
        );

        return (string) preg_replace_callback(
            self::INLINE_PATTERN,
            fn (array $matches): string => $this->renderOrOriginal(
                $shortcodeDir,
                $matches[1],
                $matches[2],
                $entry,
                '',
                $matches[0],
            ),
            $content,
        );
    }

    private function shortcodeDirectory(Entry $entry): string
    {
        $directory = dirname($entry->filePath);
        while (true) {
            $candidate = $directory . '/shortcodes';
            if (is_dir($candidate)) {
                return $candidate;
            }

            if (is_file($directory . '/config.yaml')) {
                return '';
            }

            $parent = dirname($directory);
            if ($parent === $directory) {
                return '';
            }

            $directory = $parent;
        }
    }

    private function renderOrOriginal(
        string $shortcodeDir,
        string $name,
        string $attributeString,
        Entry $entry,
        string $content,
        string $original,
    ): string {
        $template = $shortcodeDir . '/' . $name . '.php';
        if (!is_file($template)) {
            return $original;
        }

        return $this->render($template, $name, $this->parseAttributes($attributeString), $content, $entry);
    }

    /**
     * @param array<string, string> $attributes
     */
    private function render(string $template, string $name, array $attributes, string $content, Entry $entry): string
    {
        ob_start();
        try {
            /** @psalm-suppress UnresolvableInclude User-defined shortcode templates are resolved at build time. */
            $result = require $template;
            $output = (string) ob_get_clean();
        } catch (Throwable $throwable) {
            ob_get_clean();
            throw $throwable;
        }

        return $result === 1 ? $output : $output . (string) $result;
    }
}
