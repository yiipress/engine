<article>
    <h1><?= $entry->getTitle() ?></h1>

    <?php if ($entry->getDate() !== null): ?>
        <time datetime="<?= $entry->getDate()->format('Y-m-d') ?>">
            <?= $entry->getDate()->format('F j, Y') ?>
        </time>
    <?php endif ?>

    <div><?= $entry->getHtml() ?></div>

    <?php if ($entry->getTags() !== []): ?>
        <ul>
            <?php foreach ($entry->getTags() as $tag): ?>
                <li><a href="/tags/<?= $tag ?>/"><?= $tag ?></a></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</article>
