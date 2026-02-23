<?php

declare(strict_types=1);

namespace App\Content\Parser;

use App\Content\Model\Author;
use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\Model\Navigation;
use App\Content\Model\SiteConfig;
use FilesystemIterator;
use Generator;
use SplFileInfo;

final class ContentParser
{
    private SiteConfigParser $siteConfigParser;
    private CollectionConfigParser $collectionConfigParser;
    private NavigationParser $navigationParser;
    private EntryParser $entryParser;
    private AuthorParser $authorParser;

    public function __construct(
        private ?FrontMatterParser $frontMatterParser = new FrontMatterParser(),
        private ?FilenameParser $filenameParser = new FilenameParser(),
    ) {
        $this->siteConfigParser = new SiteConfigParser();
        $this->collectionConfigParser = new CollectionConfigParser();
        $this->navigationParser = new NavigationParser();
        $this->entryParser = new EntryParser($this->frontMatterParser, $this->filenameParser);
        $this->authorParser = new AuthorParser($this->frontMatterParser);
    }

    public function setAuthors(array $authors): void
    {
        $this->entryParser = new EntryParser(
            $this->frontMatterParser,
            $this->filenameParser,
            $authors
        );
    }

    public function parseSiteConfig(string $contentDir): SiteConfig
    {
        return $this->siteConfigParser->parse($contentDir . '/config.yaml');
    }

    public function parseNavigation(string $contentDir): Navigation
    {
        $path = $contentDir . '/navigation.yaml';
        if (!file_exists($path)) {
            return new Navigation(menus: []);
        }

        return $this->navigationParser->parse($path);
    }

    /**
     * @return array<string, Collection>
     */
    public function parseCollections(string $contentDir): array
    {
        $collections = [];

        $iterator = new FilesystemIterator($contentDir, FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if (!$item->isDir()) {
                continue;
            }

            $name = $item->getFilename();
            if ($name === 'assets' || $name === 'authors') {
                continue;
            }

            $configPath = $item->getPathname() . '/_collection.yaml';
            if (!file_exists($configPath)) {
                continue;
            }

            $collections[$name] = $this->collectionConfigParser->parse($configPath, $name);
        }

        return $collections;
    }

    /**
     * @return Generator<Entry>
     */
    public function parseStandalonePages(string $contentDir): Generator
    {
        $iterator = new FilesystemIterator($contentDir, FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if ($item->isDir()) {
                continue;
            }

            if ($item->getExtension() !== 'md') {
                continue;
            }

            yield $this->entryParser->parse($item->getPathname(), '');
        }
    }

    /**
     * @return Generator<Entry>
     */
    public function parseAllEntries(string $contentDir): Generator
    {
        $iterator = new FilesystemIterator($contentDir, FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if (!$item->isDir()) {
                continue;
            }

            $name = $item->getFilename();
            if ($name === 'assets' || $name === 'authors') {
                continue;
            }

            $configPath = $item->getPathname() . '/_collection.yaml';
            if (!file_exists($configPath)) {
                continue;
            }

            yield from $this->parseEntries($contentDir, $name);
        }
    }

    /**
     * @return Generator<Entry>
     */
    public function parseEntries(string $contentDir, string $collectionName): Generator
    {
        $collectionDir = $contentDir . '/' . $collectionName;

        $iterator = new FilesystemIterator($collectionDir, FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if ($item->isDir()) {
                continue;
            }

            if ($item->getExtension() !== 'md') {
                continue;
            }

            yield $this->entryParser->parse($item->getPathname(), $collectionName);
        }
    }

    /**
     * @return Generator<string, Author>
     */
    public function parseAuthors(string $contentDir): Generator
    {
        $authorsDir = $contentDir . '/authors';
        if (!is_dir($authorsDir)) {
            return;
        }

        $iterator = new FilesystemIterator($authorsDir, FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if ($item->isDir()) {
                continue;
            }

            if ($item->getExtension() !== 'md') {
                continue;
            }

            $author = $this->authorParser->parse($item->getPathname());
            yield $author->slug => $author;
        }
    }
}
