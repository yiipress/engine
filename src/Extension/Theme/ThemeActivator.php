<?php

declare(strict_types=1);

namespace YiiPress\Extension\Theme;

use YiiPress\Extension\Install\InstallException;

use function count;
use function implode;
use function in_array;
use function is_array;
use function ltrim;
use function preg_match;
use function preg_split;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;
use function trim;
use function yaml_parse;

use const PREG_SPLIT_NO_EMPTY;

/**
 * Sets the top-level `theme` key of `config.yaml` by editing the text, so comments and layout stay.
 *
 * Anything that cannot be changed safely as one line is `config_unsupported`: the user edits it by hand.
 */
final class ThemeActivator
{
    private const string BOM = "\xEF\xBB\xBF";

    public function activate(string $configYaml, string $themeName): string
    {
        $bom = str_starts_with($configYaml, self::BOM) ? self::BOM : '';
        $text = substr($configYaml, strlen($bom));
        $eol = str_contains($text, "\r\n") ? "\r\n" : "\n";
        /** @var list<string> $lines */
        $lines = preg_split('/(?<=\n)/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $this->rejectTopLevelFlowMapping($lines);
        $matches = [];
        foreach ($lines as $index => $line) {
            if (str_starts_with($line, 'theme:')) {
                $matches[] = $index;
            }
        }
        if (count($matches) > 1) {
            throw self::unsupported(sprintf('it has %d top-level "theme:" lines', count($matches)));
        }

        if ($matches === []) {
            $separator = $text === '' || str_ends_with($text, "\n") ? '' : $eol;
            $new = $text . $separator . 'theme: ' . self::scalar($themeName) . $eol;
        } else {
            $index = $matches[0];
            $lines[$index] = $this->replaceLine($lines[$index], $lines[$index + 1] ?? null, $themeName);
            $new = implode('', $lines);
        }

        $this->checkOnlyThemeChanged($text, $new, $themeName);

        return $bom . $new;
    }

    /**
     * @param list<string> $lines
     */
    private function rejectTopLevelFlowMapping(array $lines): void
    {
        foreach ($lines as $line) {
            $content = trim($line);
            if ($content === '' || str_starts_with($content, '#') || $content === '---') {
                continue;
            }
            if (str_starts_with($content, '{')) {
                throw self::unsupported('its top level is a flow mapping');
            }
            return;
        }
    }

    private function replaceLine(string $line, ?string $next, string $themeName): string
    {
        $ending = str_ends_with($line, "\r\n") ? "\r\n" : (str_ends_with($line, "\n") ? "\n" : '');
        $rest = substr($line, strlen('theme:'), strlen($line) - strlen('theme:') - strlen($ending));
        if ($rest !== '' && $rest[0] !== ' ' && $rest[0] !== "\t") {
            throw self::unsupported(sprintf('line "%s" is not a plain key', rtrim($line)));
        }
        [$value, $comment] = $this->splitValue(ltrim($rest, " \t"), $line);
        if (
            $next !== null
            && trim($next) !== ''
            && (str_starts_with($next, ' ') || str_starts_with($next, "\t") || str_starts_with($next, '-'))
            && ($value === '' || !str_starts_with(ltrim($next), '#'))
        ) {
            throw self::unsupported(sprintf('the value of "%s" continues on the next line', rtrim($line)));
        }

        return 'theme: ' . self::scalar($themeName) . ($comment === '' ? '' : ' ' . $comment) . $ending;
    }

    /**
     * @return array{string, string} Value and comment.
     */
    private function splitValue(string $rest, string $line): array
    {
        if ($rest === '' || $rest[0] === '#') {
            return ['', $rest];
        }
        if (in_array($rest[0], ['&', '*', '!', '|', '>', '[', '{', '?', '@', '`'], true)) {
            throw self::unsupported(sprintf(
                'line "%s" uses an anchor, alias, tag, block scalar or flow value',
                rtrim($line),
            ));
        }
        $pattern = match ($rest[0]) {
            '"' => '/^("(?:[^"\\\\]|\\\\.)*")[ \t]*(#.*)?$/D',
            "'" => "/^('(?:[^']|'')*')[ \t]*(#.*)?$/D",
            default => '/^([^#]*?)[ \t]*((?<=[ \t])#.*)?$/D',
        };
        if (preg_match($pattern, rtrim($rest, " \t"), $matches) !== 1) {
            throw self::unsupported(sprintf('the value of "%s" spans more than one line', rtrim($line)));
        }
        if ($rest[0] !== '"' && $rest[0] !== "'" && strpos($matches[1], ': ') !== false) {
            throw self::unsupported(sprintf('line "%s" is not a single value', rtrim($line)));
        }

        return [$matches[1], $matches[2] ?? ''];
    }

    /**
     * The name as a YAML value: plain, or double-quoted when YAML would read it as a number, boolean or null.
     */
    private static function scalar(string $themeName): string
    {
        $parsed = @yaml_parse('theme: ' . $themeName);

        return is_array($parsed) && ($parsed['theme'] ?? null) === $themeName ? $themeName : '"' . $themeName . '"';
    }

    private function checkOnlyThemeChanged(string $old, string $new, string $themeName): void
    {
        $before = $old === '' ? [] : @yaml_parse($old);
        $after = @yaml_parse($new);
        if ($before === null) {
            $before = [];
        }
        if (!is_array($before) || !is_array($after) || ($after['theme'] ?? null) !== $themeName) {
            throw self::unsupported('the result does not set theme as expected');
        }
        unset($before['theme'], $after['theme']);
        if ($before !== $after) {
            throw self::unsupported('changing the theme line would change other settings');
        }
    }

    private static function unsupported(string $reason): InstallException
    {
        return new InstallException(
            'config_unsupported',
            sprintf('config.yaml cannot be changed safely: %s.', $reason),
            solution: 'Set "theme: <name>" in config.yaml by hand, and use --no-activate meanwhile.',
        );
    }
}
