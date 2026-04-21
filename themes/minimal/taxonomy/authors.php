<?php

use App\I18n\UiText;

$uiLanguage ??= 'en';
$ui ??= UiText::for($uiLanguage);
$t ??= static fn (string $key, array $params = []): string => $ui->get($key, $params);
?>
<h1 data-ui-key="authors"><?= htmlspecialchars($t('authors')) ?></h1>

<ul>
    <?php foreach ($authors as $author): ?>
        <li><a href="/authors/<?= $author->getSlug() ?>/"><?= $author->getTitle() ?></a></li>
    <?php endforeach ?>
</ul>
