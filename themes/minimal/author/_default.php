<?php

use App\I18n\UiText;

$uiLanguage ??= 'en';
$ui ??= UiText::for($uiLanguage);
$t ??= static fn (string $key, array $params = []): string => $ui->get($key, $params);
?>
<article>
    <h1><?= $author->getTitle() ?></h1>

    <?php if ($author->getHtml() !== ''): ?>
        <div><?= $author->getHtml() ?></div>
    <?php endif ?>

    <h2 data-ui-key="posts"><?= htmlspecialchars($t('posts')) ?></h2>

    <?php foreach ($entries as $entry): ?>
        <?php include __DIR__ . '/../partials/entry-card.php' ?>
    <?php endforeach ?>
</article>
