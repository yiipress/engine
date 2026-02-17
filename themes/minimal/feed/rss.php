<?= '<?xml version="1.0" encoding="UTF-8"?>' ?>

<rss version="2.0">
    <channel>
        <title><?= $collection->getTitle() ?></title>
        <link><?= $config['base_url'] ?></link>
        <description><?= $collection->getDescription() ?></description>
        <?php foreach ($collection->getEntries() as $entry): ?>
            <item>
                <title><?= $entry->getTitle() ?></title>
                <link><?= $config['base_url'] . $entry->getPermalink() ?></link>
                <?php if ($entry->getDate() !== null): ?>
                    <pubDate><?= $entry->getDate()->format('r') ?></pubDate>
                <?php endif ?>
                <description><?= htmlspecialchars($entry->getSummary()) ?></description>
            </item>
        <?php endforeach ?>
    </channel>
</rss>
