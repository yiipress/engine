<?= '<?xml version="1.0" encoding="UTF-8"?>' ?>

<feed xmlns="http://www.w3.org/2005/Atom">
    <title><?= $collection->getTitle() ?></title>
    <link href="<?= $config['base_url'] ?>"/>
    <id><?= $config['base_url'] ?></id>
    <?php foreach ($collection->getEntries() as $entry): ?>
        <entry>
            <title><?= $entry->getTitle() ?></title>
            <link href="<?= $config['base_url'] . $entry->getPermalink() ?>"/>
            <id><?= $config['base_url'] . $entry->getPermalink() ?></id>
            <?php if ($entry->getDate() !== null): ?>
                <updated><?= $entry->getDate()->format('c') ?></updated>
            <?php endif ?>
            <summary><?= htmlspecialchars($entry->getSummary()) ?></summary>
        </entry>
    <?php endforeach ?>
</feed>
