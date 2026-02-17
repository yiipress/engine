<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\Model\SiteConfig;
use App\Content\PermalinkResolver;
use App\Processor\ContentProcessorPipeline;
use DateTimeImmutable;
use DateTimeInterface;
use XMLWriter;

final class FeedGenerator
{
    private XMLWriter $xml;

    public function __construct(
        private readonly ContentProcessorPipeline $pipeline,
        private readonly array $authors = [],
    ) {
        $this->xml = new XMLWriter();
        $this->xml->openMemory();
        $this->xml->setIndent(true);
        $this->xml->setIndentString('  ');
    }

    /**
     * @param list<Entry> $entries
     */
    public function generateAtom(
        SiteConfig $siteConfig,
        Collection $collection,
        array $entries,
    ): string {
        // Reset XMLWriter for reuse
        $this->xml->openMemory();
        $this->xml->startDocument('1.0', 'UTF-8');

        $this->xml->startElement('feed');
        $this->xml->writeAttribute('xmlns', 'http://www.w3.org/2005/Atom');

        $feedUrl = rtrim($siteConfig->baseUrl, '/') . '/' . $collection->name . '/feed.xml';
        $collectionUrl = rtrim($siteConfig->baseUrl, '/') . '/' . $collection->name . '/';

        $this->xml->writeElement('title', $collection->title);
        if ($collection->description !== '') {
            $this->xml->writeElement('subtitle', $collection->description);
        }

        $this->xml->startElement('link');
        $this->xml->writeAttribute('href', $collectionUrl);
        $this->xml->endElement();

        $this->xml->startElement('link');
        $this->xml->writeAttribute('href', $feedUrl);
        $this->xml->writeAttribute('rel', 'self');
        $this->xml->writeAttribute('type', 'application/atom+xml');
        $this->xml->endElement();

        $this->xml->writeElement('id', $collectionUrl);

        $updated = $this->resolveLatestDate($entries);
        if ($updated !== null) {
            $this->xml->writeElement('updated', $updated->format(DateTimeInterface::ATOM));
        }

        if ($siteConfig->language !== '') {
            $this->xml->startElement('generator');
            $this->xml->writeAttribute('uri', 'https://github.com/yiisoft/yiipress');
            $this->xml->text('YiiPress');
            $this->xml->endElement();
        }

        foreach ($entries as $entry) {
            $this->writeAtomEntry($this->xml, $siteConfig, $collection, $entry);
        }

        $this->xml->endElement();
        $this->xml->endDocument();

        return $this->xml->outputMemory();
    }

    /**
     * @param list<Entry> $entries
     */
    public function generateRss(
        SiteConfig $siteConfig,
        Collection $collection,
        array $entries,
    ): string {
        $this->xml = new XMLWriter();
        $this->xml->openMemory();
        $this->xml->startDocument('1.0', 'UTF-8');
        $this->xml->setIndent(true);
        $this->xml->setIndentString('  ');

        $this->xml->startElement('rss');
        $this->xml->writeAttribute('version', '2.0');
        $this->xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $this->xml->writeAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');

        $feedUrl = rtrim($siteConfig->baseUrl, '/') . '/' . $collection->name . '/rss.xml';
        $collectionUrl = rtrim($siteConfig->baseUrl, '/') . '/' . $collection->name . '/';

        $this->xml->startElement('channel');
        $this->xml->writeElement('title', $collection->title);
        $this->xml->writeElement('description', $collection->description !== '' ? $collection->description : $siteConfig->description);
        $this->xml->writeElement('link', $collectionUrl);

        if ($siteConfig->language !== '') {
            $this->xml->writeElement('language', $siteConfig->language);
        }

        $this->xml->startElement('atom:link');
        $this->xml->writeAttribute('href', $feedUrl);
        $this->xml->writeAttribute('rel', 'self');
        $this->xml->writeAttribute('type', 'application/rss+xml');
        $this->xml->endElement();

        $updated = $this->resolveLatestDate($entries);
        if ($updated !== null) {
            $this->xml->writeElement('lastBuildDate', $updated->format(DateTimeInterface::RSS));
        }

        foreach ($entries as $entry) {
            $this->writeRssItem($this->xml, $siteConfig, $collection, $entry);
        }

        $this->xml->endElement();
        $this->xml->endElement();
        $this->xml->endDocument();

        return $this->xml->outputMemory();
    }

    private function writeAtomEntry(
        XMLWriter $xml,
        SiteConfig $siteConfig,
        Collection $collection,
        Entry $entry,
    ): void {
        $entryUrl = $this->resolveEntryUrl($siteConfig, $collection, $entry);

        $this->xml->startElement('entry');
        $this->xml->writeElement('title', $entry->title);

        $this->xml->startElement('link');
        $this->xml->writeAttribute('href', $entryUrl);
        $this->xml->endElement();

        $this->xml->writeElement('id', $entryUrl);

        if ($entry->date !== null) {
            $this->xml->writeElement('published', $entry->date->format(DateTimeInterface::ATOM));
            $this->xml->writeElement('updated', $entry->date->format(DateTimeInterface::ATOM));
        }

        foreach ($entry->authors as $authorSlug) {
            $authorName = $this->authors[$authorSlug]->title ?? $authorSlug;
            $this->xml->startElement('author');
            $this->xml->writeElement('name', $authorName);
            $this->xml->endElement();
        }

        $summary = $entry->summary();
        if ($summary !== '') {
            $this->xml->writeElement('summary', $summary);
        }

        $html = $this->pipeline->process($entry->body(), $entry);
        if ($html !== '') {
            $this->xml->startElement('content');
            $this->xml->writeAttribute('type', 'html');
            $this->xml->text($html);
            $this->xml->endElement();
        }

        $this->xml->endElement();
    }

    private function writeRssItem(
        XMLWriter $xml,
        SiteConfig $siteConfig,
        Collection $collection,
        Entry $entry,
    ): void {
        $entryUrl = $this->resolveEntryUrl($siteConfig, $collection, $entry);

        $this->xml->startElement('item');
        $this->xml->writeElement('title', $entry->title);
        $this->xml->writeElement('link', $entryUrl);
        $this->xml->writeElement('guid', $entryUrl);

        if ($entry->date !== null) {
            $this->xml->writeElement('pubDate', $entry->date->format(DateTimeInterface::RSS));
        }

        $summary = $entry->summary();
        if ($summary !== '') {
            $this->xml->writeElement('description', $summary);
        }

        $html = $this->pipeline->process($entry->body(), $entry);
        if ($html !== '') {
            $this->xml->writeElement('content:encoded', $html);
        }

        $this->xml->endElement();
    }

    private function resolveEntryUrl(SiteConfig $siteConfig, Collection $collection, Entry $entry): string
    {
        return rtrim($siteConfig->baseUrl, '/') . PermalinkResolver::resolve($entry, $collection);
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
}
