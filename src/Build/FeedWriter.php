<?php

declare(strict_types=1);

namespace YiiPress\Build;

use YiiPress\Content\Model\Author;
use YiiPress\Content\Model\Collection;
use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;
use YiiPress\Processor\ContentProcessorPipeline;
use RuntimeException;

use function is_dir;
use function mkdir;
use function sprintf;

final readonly class FeedWriter
{
    /**
     * @param array<string, Author> $authors
     */
    public function __construct(
        private ContentProcessorPipeline $feedPipeline,
        private array $authors,
    ) {}

    /**
     * @param array{collectionName: string, collection: Collection, entries: list<Entry>} $feedTask
     */
    public function writeTask(array $feedTask, SiteConfig $siteConfig, string $outputDir, bool $noWrite): int
    {
        $collection = $feedTask['collection'];
        $entries = $feedTask['entries'];
        $collectionName = $feedTask['collectionName'];

        $feedGenerator = new FeedGenerator($this->feedPipeline, $this->authors);

        if ($noWrite) {
            $feedGenerator->generateAtom($siteConfig, $collection, $entries);
            $feedGenerator->generateRss($siteConfig, $collection, $entries);
            $feedGenerator->generateJson($siteConfig, $collection, $entries);

            return 1;
        }

        $feedDir = $outputDir . '/' . $collectionName;
        if (!is_dir($feedDir) && !mkdir($feedDir, 0o755, true) && !is_dir($feedDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $feedDir));
        }

        $feedGenerator->writeAtomFile($feedDir . '/feed.xml', $siteConfig, $collection, $entries);
        $feedGenerator->writeRssFile($feedDir . '/rss.xml', $siteConfig, $collection, $entries);
        $feedGenerator->writeJsonFile($feedDir . '/feed.json', $siteConfig, $collection, $entries);

        return 1;
    }
}
