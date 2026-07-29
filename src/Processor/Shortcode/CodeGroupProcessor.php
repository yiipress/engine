<?php

declare(strict_types=1);

namespace YiiPress\Processor\Shortcode;

use YiiPress\Content\Model\Entry;
use YiiPress\Processor\AssetProcessorInterface;
use YiiPress\Processor\ContentProcessorInterface;

use function htmlspecialchars;
use function implode;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function str_contains;
use function strtolower;
use function trim;

/**
 * Turns configurable code-group and code-tab shortcodes into accessible tabbed code groups.
 *
 * The same processor is intentionally run on both sides of MarkdownProcessor: the first pass
 * preserves shortcode metadata in HTML comments, and the second pass builds the final markup.
 */
final readonly class CodeGroupProcessor implements ContentProcessorInterface, AssetProcessorInterface
{
    use ParsesShortcodeAttributesTrait;

    public function __construct(
        private string $groupShortcode = 'code-group',
        private string $tabShortcode = 'code-tab',
    ) {
        if (
            preg_match('/^[a-z][a-z0-9-]*$/i', $this->groupShortcode) !== 1
            || preg_match('/^[a-z][a-z0-9-]*$/i', $this->tabShortcode) !== 1
        ) {
            throw new \InvalidArgumentException('Code group shortcode names must contain only letters, digits, and hyphens.');
        }
    }

    public function process(string $content, Entry $entry): string
    {
        if (str_contains($content, '<!-- yiipress-code-group:')) {
            return $this->renderGroups($content);
        }

        if (!str_contains(strtolower($content), '[' . strtolower($this->groupShortcode))) {
            return $content;
        }

        return $this->preserveShortcodes($content);
    }

    public function headAssets(string $processedContent): string
    {
        if (!str_contains($processedContent, 'class="code-group"')) {
            return '';
        }

        return <<<'HTML'
    <link rel="stylesheet" href="assets/plugins/code-groups.css">
    <script defer src="assets/plugins/code-groups.js"></script>

HTML;
    }

    public function assetFiles(): array
    {
        return [
            __DIR__ . '/assets/code-groups.css' => 'assets/plugins/code-groups.css',
            __DIR__ . '/assets/code-groups.js' => 'assets/plugins/code-groups.js',
        ];
    }

    private function preserveShortcodes(string $content): string
    {
        $group = preg_quote($this->groupShortcode, '/');
        $tab = preg_quote($this->tabShortcode, '/');

        return (string) preg_replace_callback(
            '/^\[' . $group . '\]\s*$([\s\S]*?)^\[\/' . $group . '\]\s*$/mi',
            function (array $groupMatch) use ($tab): string {
                $tabs = [];
                if (
                    preg_match_all(
                        $tabPattern = '/^\[' . $tab . '\s+([^\]]*)\]\s*$([\s\S]*?)^\[\/' . $tab . '\]\s*$/mi',
                        $groupMatch[1],
                        $tabMatches,
                        PREG_SET_ORDER,
                    ) < 2
                    || trim((string) preg_replace($tabPattern, '', $groupMatch[1])) !== ''
                ) {
                    return $groupMatch[0];
                }

                foreach ($tabMatches as $tabMatch) {
                    $attributes = $this->parseAttributes($tabMatch[1]);
                    $label = $attributes['label'] ?? '';
                    if ($label === '') {
                        return $groupMatch[0];
                    }
                    $tabs[] = '<!-- yiipress-code-tab:' . base64_encode($label) . " -->\n"
                        . trim($tabMatch[2]) . "\n"
                        . '<!-- /yiipress-code-tab -->';
                }

                return "<!-- yiipress-code-group:start -->\n"
                    . implode("\n", $tabs)
                    . "\n<!-- yiipress-code-group:end -->";
            },
            $content,
        );
    }

    private function renderGroups(string $content): string
    {
        $groupNumber = 0;

        return (string) preg_replace_callback(
            '/<!-- yiipress-code-group:start -->\s*([\s\S]*?)\s*<!-- yiipress-code-group:end -->/',
            static function (array $groupMatch) use (&$groupNumber): string {
                $tabs = [];
                preg_match_all(
                    '/<!-- yiipress-code-tab:([A-Za-z0-9+\/=]+) -->\s*([\s\S]*?)\s*<!-- \/yiipress-code-tab -->/',
                    $groupMatch[1],
                    $tabs,
                    PREG_SET_ORDER,
                );

                if (count($tabs) < 2) {
                    return $groupMatch[0];
                }

                $groupNumber++;
                $groupId = 'code-group-' . $groupNumber;
                $panels = [];

                foreach ($tabs as $index => $tab) {
                    $number = $index + 1;
                    $panelId = $groupId . '-panel-' . $number;
                    $labelId = $panelId . '-label';
                    $label = base64_decode($tab[1], true);
                    if ($label === false) {
                        return $groupMatch[0];
                    }

                    $escapedLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
                    $panels[] = sprintf(
                        '<section class="code-group-panel" id="%s" aria-labelledby="%s" data-code-group-panel data-label="%s">'
                        . '<div class="code-group-label" id="%s">%s</div>%s</section>',
                        $panelId,
                        $labelId,
                        $escapedLabel,
                        $labelId,
                        $escapedLabel,
                        trim($tab[2]),
                    );
                }

                return sprintf(
                    '<div class="code-group" data-code-group>%s</div>',
                    implode('', $panels),
                );
            },
            $content,
        );
    }
}
