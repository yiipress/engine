<article>
    <h1><?= $author->getTitle() ?></h1>

    <?php if ($author->getHtml() !== ''): ?>
        <div><?= $author->getHtml() ?></div>
    <?php endif ?>

    <h2>Posts</h2>

    <?php foreach ($entries as $entry): ?>
        <?php include __DIR__ . '/../partials/entry-card.php' ?>
    <?php endforeach ?>
</article>
