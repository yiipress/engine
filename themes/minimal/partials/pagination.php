<?php

use App\I18n\UiText;

$uiLanguage ??= 'en';
$ui ??= UiText::for($uiLanguage);
$t ??= static fn (string $key, array $params = []): string => $ui->get($key, $params);
?>
<?php if ($collection->getPagination() !== null): ?>
    <?php $pagination = $collection->getPagination() ?>
    <nav>
        <?php if ($pagination->hasPrevious()): ?>
            <a href="<?= $pagination->getPreviousUrl() ?>" data-ui-key="previous"><?= htmlspecialchars($t('previous')) ?></a>
        <?php endif ?>
        <?php if ($pagination->hasNext()): ?>
            <a href="<?= $pagination->getNextUrl() ?>" data-ui-key="next"><?= htmlspecialchars($t('next')) ?></a>
        <?php endif ?>
    </nav>
<?php endif ?>
