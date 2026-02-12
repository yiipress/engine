<!DOCTYPE html>
<html lang="<?= $config['language'] ?>">
<head>
    <meta charset="<?= $config['charset'] ?>">
    <title><?= $entry->getTitle() ?> — <?= $config['title'] ?></title>
</head>
<body>
    <?php include __DIR__ . '/../partials/navigation.php' ?>
    <main>
        <article>
            <?= $content ?>
        </article>
        <?php include __DIR__ . '/../partials/sidebar.php' ?>
    </main>
</body>
</html>
