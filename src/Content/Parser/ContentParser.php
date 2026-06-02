<?php

declare(strict_types=1);

namespace YiiPress\Content\Parser;

use YiiPress\Content\Model\Author;
use YiiPress\Content\Model\Collection;
use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\Navigation;
use YiiPress\Content\Model\SiteConfig;
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
        private readonly ?FrontMatterParser $frontMatterParser = new FrontMatterParser(),
        private readonly ?FilenameParser $filenameParser = new FilenameParser(),
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

    public function parseRootCollection(string $contentDir): Collection
    {
        $path = $contentDir . '/_collection.yaml';
        if (file_exists($path)) {
            $collection = $this->collectionConfigParser->parse($path, '');

            return new Collection(
                name: $collection->name,
                title: $collection->title,
                description: $collection->description,
                permalink: $collection->permalink,
                sortBy: $collection->sortBy,
                sortOrder: $collection->sortOrder,
                entriesPerPage: $collection->entriesPerPage,
                feed: false,
                listing: false,
                order: $collection->order,
                navigationPager: $collection->navigationPager,
            );
        }

        return new Collection(
            name: '',
            title: '',
            description: '',
            permalink: '/:slug/',
            sortBy: 'weight',
            sortOrder: 'asc',
            entriesPerPage: 0,
            feed: false,
            listing: false,
        );
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
