<h1><?= $collection->getTitle() ?></h1>

<?php foreach ($collection->getEntries() as $entry): ?>
    <?php include __DIR__ . '/../partials/entry-card.php' ?>
<?php endforeach ?>

<?php include __DIR__ . '/../partials/pagination.php' ?>
