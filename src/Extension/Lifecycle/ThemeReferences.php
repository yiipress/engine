<?php

declare(strict_types=1);

namespace YiiPress\Extension\Lifecycle;

use Throwable;
use YiiPress\Content\Model\Entry;
use YiiPress\Content\Parser\ContentParser;
use YiiPress\Content\Parser\SiteConfigParser;
use YiiPress\Extension\Install\InstallException;

use function is_file;
use function sprintf;

/**
 * Finds the content files that select a theme: the site `theme` in `config.yaml` and the `theme` front matter of
 * entries and standalone pages. References from custom PHP code are not found.
 *
 * Unlike {@see \YiiPress\Extension\Theme\UsedThemes}, which the build uses, it never caches and never guesses:
 * content it cannot read is an error, since the theme may be selected there.
 */
final readonly class ThemeReferences
{
    public function __construct(
        private SiteConfigParser $configParser = new SiteConfigParser(),
        private ContentParser $contentParser = new ContentParser(),
    ) {}

    /**
     * @param string $contentDir Absolute content directory, the one holding `config.yaml`.
     * @return list<string> Absolute paths of the files that select the theme, `config.yaml` first.
     *
     * @throws InstallException `content_unreadable` when `config.yaml` or an entry cannot be parsed.
     */
    public function find(string $themeName, string $contentDir): array
    {
        $files = [];
        try {
            $config = $contentDir . '/config.yaml';
            if (is_file($config) && $this->configParser->parse($config)->theme === $themeName) {
                $files[] = $config;
            }
            foreach ($this->entries($contentDir) as $entry) {
                if ($entry->theme === $themeName) {
                    $files[] = $entry->sourceFilePath();
                }
            }
        } catch (Throwable $exception) {
            throw new InstallException(
                'content_unreadable',
                sprintf(
                    'The content in "%s" could not be read, so it is unknown whether it selects theme "%s": %s',
                    $contentDir,
                    $themeName,
                    $exception->getMessage(),
                ),
                solution: 'Fix the content so that the build reads it, then run remove:theme again.',
                previous: $exception,
            );
        }

        return $files;
    }

    /**
     * @return iterable<Entry> Entries of every collection, then standalone pages.
     */
    private function entries(string $contentDir): iterable
    {
        yield from $this->contentParser->parseAllEntries($contentDir);
        yield from $this->contentParser->parseStandalonePages($contentDir);
    }
}
