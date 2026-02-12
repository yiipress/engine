<h1>Category: <?= $category ?></h1>

<?php foreach ($entries as $entry): ?>
    <?php include __DIR__ . '/../partials/entry-card.php' ?>
<?php endforeach ?>
