<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="refresh" content="0; url=<?= $entry->getRedirectTo() ?>">
    <link rel="canonical" href="<?= $entry->getRedirectTo() ?>">
    <title>Redirecting...</title>
</head>
<body>
    <p>Redirecting to <a href="<?= $entry->getRedirectTo() ?>"><?= $entry->getRedirectTo() ?></a>.</p>
</body>
</html>
