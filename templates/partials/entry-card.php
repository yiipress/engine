<article>
    <h2><a href="<?= $entry->getPermalink() ?>"><?= $entry->getTitle() ?></a></h2>

    <?php if ($entry->getDate() !== null): ?>
        <time datetime="<?= $entry->getDate()->format('Y-m-d') ?>">
            <?= $entry->getDate()->format('F j, Y') ?>
        </time>
    <?php endif ?>

    <?php if ($entry->getSummary() !== ''): ?>
        <p><?= $entry->getSummary() ?></p>
    <?php endif ?>
</article>
