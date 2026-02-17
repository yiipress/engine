<h1>Tags</h1>

<ul>
    <?php foreach ($tags as $tag => $entries): ?>
        <li><a href="/tags/<?= $tag ?>/"><?= $tag ?></a> (<?= count($entries) ?>)</li>
    <?php endforeach ?>
</ul>
