<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\Model\SiteConfig;
use App\Render\MarkdownRenderer;
use DateTimeInterface;
use XMLWriter;

final class FeedGenerator
{
    private MarkdownRenderer $markdownRenderer;

    public function __construct()
    {
        $this->markdownRenderer = new MarkdownRenderer();
    }

    /**
     * @param list<Entry> $entries
     */
    public function generateAtom(
        SiteConfig $siteConfig,
        Collection $collection,
        array $entries,
    ): string {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->setIndent(true);
        $xml->setIndentString('  ');

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

        if ($siteConfig->language !== '') {
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
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->setIndent(true);
        $xml->setIndentString('  ');

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

        if ($siteConfig->language !== '') {
            $xml->writeElement('language', $siteConfig->language);
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

        return $xml->outputMemory();
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

        foreach ($entry->authors as $authorName) {
            $xml->startElement('author');
            $xml->writeElement('name', $authorName);
            $xml->endElement();
        }

        $summary = $entry->summary();
        if ($summary !== '') {
            $xml->writeElement('summary', $summary);
        }

        $html = $this->markdownRenderer->render($entry->body());
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

        $html = $this->markdownRenderer->render($entry->body());
        if ($html !== '') {
            $xml->writeElement('content:encoded', $html);
        }

        $xml->endElement();
    }

    private function resolveEntryUrl(SiteConfig $siteConfig, Collection $collection, Entry $entry): string
    {
        $permalink = $entry->permalink !== ''
            ? $entry->permalink
            : str_replace(
                [':collection', ':slug'],
                [$collection->name, $entry->slug],
                $collection->permalink,
            );

        return rtrim($siteConfig->baseUrl, '/') . $permalink;
    }

    /**
     * @param list<Entry> $entries
     */
    private function resolveLatestDate(array $entries): ?\DateTimeImmutable
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
