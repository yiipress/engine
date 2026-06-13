<?php

declare(strict_types=1);

namespace YiiPress\Build;

use YiiPress\Content\Model\Author;
use YiiPress\Content\Model\Collection;
use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;
use YiiPress\Content\PermalinkResolver;
use YiiPress\Processor\ContentProcessorPipeline;
use DateTimeImmutable;
use DateTimeInterface;
use XMLWriter;

use function array_slice;
use function file_put_contents;
use function json_encode;

final class FeedGenerator
{
    /** @var array<string, string> */
    private array $renderedContentCache = [];

    /**
     * @param array<string, Author> $authors
     */
    public function __construct(
        private readonly ContentProcessorPipeline $pipeline,
        private readonly array $authors = [],
    ) {}

    /**
     * @param list<Entry> $entries
     */
    public function generateAtom(
        SiteConfig $siteConfig,
        Collection $collection,
        array $entries,
    ): string {
        $xml = $this->createInMemoryWriter();
        $this->writeAtomDocument($xml, $siteConfig, $collection, $this->limitEntries($collection, $entries));

        return $xml->outputMemory();
    }

    /**
     * @param list<Entry> $entries
     */
    public function generateRss(
        SiteConfig $siteConfig,
        Collection $collection,
        array $entries,
    ): string {
        $xml = $this->createInMemoryWriter();
        $this->writeRssDocument($xml, $siteConfig, $collection, $this->limitEntries($collection, $entries));

        return $xml->outputMemory();
    }

    /**
     * @param list<Entry> $entries
     */
    public function generateJson(
        SiteConfig $siteConfig,
        Collection $collection,
        array $entries,
    ): string {
        return json_encode(
            $this->jsonDocument($siteConfig, $collection, $this->limitEntries($collection, $entries)),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * @param list<Entry> $entries
     */
    public function writeAtomFile(
        string $path,
        SiteConfig $siteConfig,
        Collection $collection,
        array $entries,
    ): void {
        $xml = $this->createFileWriter($path);
        $this->writeAtomDocument($xml, $siteConfig, $collection, $this->limitEntries($collection, $entries));
        $xml->flush();
    }

    /**
     * @param list<Entry> $entries
     */
    public function writeRssFile(
        string $path,
        SiteConfig $siteConfig,
        Collection $collection,
        array $entries,
    ): void {
        $xml = $this->createFileWriter($path);
        $this->writeRssDocument($xml, $siteConfig, $collection, $this->limitEntries($collection, $entries));
        $xml->flush();
    }

    /**
     * @param list<Entry> $entries
     */
    public function writeJsonFile(
        string $path,
        SiteConfig $siteConfig,
        Collection $collection,
        array $entries,
    ): void {
        file_put_contents($path, $this->generateJson($siteConfig, $collection, $entries));
    }

    /**
     * @param list<Entry> $entries
     */
    private function writeAtomDocument(
        XMLWriter $xml,
        SiteConfig $siteConfig,
        Collection $collection,
        array $entries,
    ): void {
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('feed');
        $xml->writeAttribute('xmlns', 'http://www.w3.org/2005/Atom');

        $feedUrl = rtrim($siteConfig->baseUrl, '/') . '/' . $collection->name . '/feed.xml';
        $collectionUrl = rtrim($siteConfig->baseUrl, '/') . '/' . $collection->name . '/';

        $xml->writeElement('title', $collection->title);
        if ($collection->description !== '') {
            $xml->writeElement('subtitle', $collection->description);
        }

        $xml->startElement('link');
        $xml->writeAttribute('href', $collectionUrl);
        $xml->endElement();

        $xml->startElement('link');
        $xml->writeAttribute('href', $feedUrl);
        $xml->writeAttribute('rel', 'self');
        $xml->writeAttribute('type', 'application/atom+xml');
        $xml->endElement();

        $xml->writeElement('id', $collectionUrl);

        $updated = $this->resolveLatestDate($entries);
        if ($updated !== null) {
            $xml->writeElement('updated', $updated->format(DateTimeInterface::ATOM));
        }

        if ($siteConfig->defaultLanguage !== '') {
            $xml->startElement('generator');
            $xml->writeAttribute('uri', 'https://github.com/yiisoft/yiipress');
            $xml->text('YiiPress');
            $xml->endElement();
        }

        foreach ($entries as $entry) {
            $this->writeAtomEntry($xml, $siteConfig, $collection, $entry);
        }

        $xml->endElement();
        $xml->endDocument();
    }

    /**
     * @param list<Entry> $entries
     */
    private function writeRssDocument(
        XMLWriter $xml,
        SiteConfig $siteConfig,
        Collection $collection,
        array $entries,
    ): void {
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $xml->writeAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');

        $feedUrl = rtrim($siteConfig->baseUrl, '/') . '/' . $collection->name . '/rss.xml';
        $collectionUrl = rtrim($siteConfig->baseUrl, '/') . '/' . $collection->name . '/';

        $xml->startElement('channel');
        $xml->writeElement('title', $collection->title);
        $xml->writeElement('description', $collection->description !== '' ? $collection->description : $siteConfig->description);
        $xml->writeElement('link', $collectionUrl);

        if ($siteConfig->defaultLanguage !== '') {
            $xml->writeElement('language', $siteConfig->defaultLanguage);
        }

        $xml->startElement('atom:link');
        $xml->writeAttribute('href', $feedUrl);
        $xml->writeAttribute('rel', 'self');
        $xml->writeAttribute('type', 'application/rss+xml');
        $xml->endElement();

        $updated = $this->resolveLatestDate($entries);
        if ($updated !== null) {
            $xml->writeElement('lastBuildDate', $updated->format(DateTimeInterface::RSS));
        }

        foreach ($entries as $entry) {
            $this->writeRssItem($xml, $siteConfig, $collection, $entry);
        }

        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();
    }

    private function writeAtomEntry(
        XMLWriter $xml,
        SiteConfig $siteConfig,
        Collection $collection,
        Entry $entry,
    ): void {
        $entryUrl = $this->resolveEntryUrl($siteConfig, $collection, $entry);

        $xml->startElement('entry');
        $xml->writeElement('title', $entry->title);

        $xml->startElement('link');
        $xml->writeAttribute('href', $entryUrl);
        $xml->endElement();

        $xml->writeElement('id', $entryUrl);

        if ($entry->date !== null) {
            $xml->writeElement('published', $entry->date->format(DateTimeInterface::ATOM));
            $xml->writeElement('updated', $entry->date->format(DateTimeInterface::ATOM));
        }

        foreach ($entry->authors as $authorSlug) {
            $authorName = $this->authors[$authorSlug]->title ?? $authorSlug;
            $xml->startElement('author');
            $xml->writeElement('name', $authorName);
            $xml->endElement();
        }

        $summary = $entry->summary();
        if ($summary !== '') {
            $xml->writeElement('summary', $summary);
        }

        $html = $this->renderedContent($siteConfig, $entry);
        if ($html !== '') {
            $xml->startElement('content');
            $xml->writeAttribute('type', 'html');
            $xml->text($html);
            $xml->endElement();
        }

        $xml->endElement();
    }

    private function writeRssItem(
        XMLWriter $xml,
        SiteConfig $siteConfig,
        Collection $collection,
        Entry $entry,
    ): void {
        $entryUrl = $this->resolveEntryUrl($siteConfig, $collection, $entry);

        $xml->startElement('item');
        $xml->writeElement('title', $entry->title);
        $xml->writeElement('link', $entryUrl);
        $xml->writeElement('guid', $entryUrl);

        if ($entry->date !== null) {
            $xml->writeElement('pubDate', $entry->date->format(DateTimeInterface::RSS));
        }

        $summary = $entry->summary();
        if ($summary !== '') {
            $xml->writeElement('description', $summary);
        }

        $html = $this->renderedContent($siteConfig, $entry);
        if ($html !== '') {
            $xml->writeElement('content:encoded', $html);
        }

        $xml->endElement();
    }

    /**
     * @param list<Entry> $entries
     * @return array<string, mixed>
     */
    private function jsonDocument(SiteConfig $siteConfig, Collection $collection, array $entries): array
    {
        $collectionUrl = rtrim($siteConfig->baseUrl, '/') . '/' . $collection->name . '/';
        $document = [
            'version' => 'https://jsonfeed.org/version/1.1',
            'title' => $collection->title,
            'home_page_url' => $collectionUrl,
            'feed_url' => rtrim($siteConfig->baseUrl, '/') . '/' . $collection->name . '/feed.json',
            'items' => array_map(
                fn (Entry $entry): array => $this->jsonItem($siteConfig, $collection, $entry),
                $entries,
            ),
        ];

        $description = $collection->description !== '' ? $collection->description : $siteConfig->description;
        if ($description !== '') {
            $document['description'] = $description;
        }

        if ($siteConfig->defaultLanguage !== '') {
            $document['language'] = $siteConfig->defaultLanguage;
        }

        return $document;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonItem(SiteConfig $siteConfig, Collection $collection, Entry $entry): array
    {
        $entryUrl = $this->resolveEntryUrl($siteConfig, $collection, $entry);
        $item = [
            'id' => $entryUrl,
            'url' => $entryUrl,
            'title' => $entry->title,
        ];

        $summary = $entry->summary();
        if ($summary !== '') {
            $item['summary'] = $summary;
        }

        $html = $this->renderedContent($siteConfig, $entry);
        if ($html !== '') {
            $item['content_html'] = $html;
        }

        if ($entry->date !== null) {
            $item['date_published'] = $entry->date->format(DateTimeInterface::ATOM);
            $item['date_modified'] = $entry->date->format(DateTimeInterface::ATOM);
        }

        if ($entry->authors !== []) {
            $item['authors'] = array_map(
                fn (string $authorSlug): array => ['name' => $this->authors[$authorSlug]->title ?? $authorSlug],
                $entry->authors,
            );
        }

        if ($entry->tags !== []) {
            $item['tags'] = $entry->tags;
        }

        return $item;
    }

    private function resolveEntryUrl(SiteConfig $siteConfig, Collection $collection, Entry $entry): string
    {
        return rtrim($siteConfig->baseUrl, '/') . PermalinkResolver::resolve($entry, $collection, $siteConfig->i18n);
    }

    /**
     * @param list<Entry> $entries
     * @return list<Entry>
     */
    private function limitEntries(Collection $collection, array $entries): array
    {
        return $collection->feedLimit <= 0 ? $entries : array_slice($entries, 0, $collection->feedLimit);
    }

    /**
     * @param list<Entry> $entries
     */
    private function resolveLatestDate(array $entries): ?DateTimeImmutable
    {
        $latest = null;
        foreach ($entries as $entry) {
            if ($entry->date !== null && ($latest === null || $entry->date > $latest)) {
                $latest = $entry->date;
            }
        }

        return $latest;
    }

    private function createInMemoryWriter(): XMLWriter
    {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');

        return $xml;
    }

    private function createFileWriter(string $path): XMLWriter
    {
        $xml = new XMLWriter();
        $xml->openUri($path);
        $xml->setIndent(true);
        $xml->setIndentString('  ');

        return $xml;
    }

    private function renderedContent(SiteConfig $siteConfig, Entry $entry): string
    {
        $rootPath = UrlResolver::absoluteUrl($siteConfig, '/');
        $cacheKey = $entry->filePath . ':' . $entry->slug . ':' . $rootPath;

        return $this->renderedContentCache[$cacheKey] ?? ($this->renderedContentCache[$cacheKey] = $this->pipeline->process(
            $entry->body(),
            $entry,
            $rootPath,
        ));
    }
}
