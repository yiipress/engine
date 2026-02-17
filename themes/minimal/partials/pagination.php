<?php if ($collection->getPagination() !== null): ?>
    <?php $pagination = $collection->getPagination() ?>
    <nav>
        <?php if ($pagination->hasPrevious()): ?>
            <a href="<?= $pagination->getPreviousUrl() ?>">Previous</a>
        <?php endif ?>
        <?php if ($pagination->hasNext()): ?>
            <a href="<?= $pagination->getNextUrl() ?>">Next</a>
        <?php endif ?>
    </nav>
<?php endif ?>
