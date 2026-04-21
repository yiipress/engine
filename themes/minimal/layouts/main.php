<!DOCTYPE html>
<html lang="<?= $config['languages'][0] ?? 'en' ?>">
<head>
    <meta charset="<?= $config['charset'] ?>">
    <title><?= $title ?? $config['title'] ?></title>
</head>
<body>
    <?php include __DIR__ . '/../partials/navigation.php' ?>
    <main><?= $content ?></main>
</body>
</html>
