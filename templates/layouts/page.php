<!DOCTYPE html>
<html lang="<?= $config['language'] ?>">
<head>
    <meta charset="<?= $config['charset'] ?>">
    <title><?= $title ?? '' ?> — <?= $config['title'] ?></title>
</head>
<body>
    <?php include __DIR__ . '/../partials/navigation.php' ?>
    <main>
        <article>
            <h1><?= $title ?? '' ?></h1>
            <?= $content ?>
        </article>
    </main>
</body>
</html>
