<h1><?= $year ?>-<?= str_pad((string) $month, 2, '0', STR_PAD_LEFT) ?></h1>

<?php foreach ($entries as $entry): ?>
    <?php include __DIR__ . '/../partials/entry-card.php' ?>
<?php endforeach ?>
