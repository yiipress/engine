<h1>Authors</h1>

<ul>
    <?php foreach ($authors as $author): ?>
        <li><a href="/authors/<?= $author->getSlug() ?>/"><?= $author->getTitle() ?></a></li>
    <?php endforeach ?>
</ul>
