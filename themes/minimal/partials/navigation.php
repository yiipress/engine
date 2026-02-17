<nav>
    <ul>
        <?php foreach ($navigation['main'] ?? [] as $item): ?>
            <li><a href="<?= $item['url'] ?>"><?= $item['title'] ?></a></li>
        <?php endforeach ?>
    </ul>
</nav>
