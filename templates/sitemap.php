<?= '<?xml version="1.0" encoding="UTF-8"?>' ?>

<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <?php foreach ($entries as $entry): ?>
        <url>
            <loc><?= $config['base_url'] . $entry->getPermalink() ?></loc>
            <?php if ($entry->getDate() !== null): ?>
                <lastmod><?= $entry->getDate()->format('Y-m-d') ?></lastmod>
            <?php endif ?>
        </url>
    <?php endforeach ?>
</urlset>
