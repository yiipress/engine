<h1>Tag: <?= $tag ?></h1>

<?php foreach ($entries as $entry): ?>
    <?php include __DIR__ . '/../partials/entry-card.php' ?>
<?php endforeach ?>
