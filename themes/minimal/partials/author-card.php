<div>
    <strong><?= $author->getTitle() ?></strong>
    <?php if ($author->getHtml() !== ''): ?>
        <div><?= $author->getHtml() ?></div>
    <?php endif ?>
</div>
