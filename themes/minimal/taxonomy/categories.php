<h1>Categories</h1>

<ul>
    <?php foreach ($categories as $category => $entries): ?>
        <li><a href="/categories/<?= $category ?>/"><?= $category ?></a> (<?= count($entries) ?>)</li>
    <?php endforeach ?>
</ul>
