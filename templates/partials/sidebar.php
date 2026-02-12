<aside>
    <?php if (isset($entry) && $entry->getAuthors() !== []): ?>
        <?php foreach ($entry->getAuthors() as $author): ?>
            <?php include __DIR__ . '/author-card.php' ?>
        <?php endforeach ?>
    <?php endif ?>
</aside>
