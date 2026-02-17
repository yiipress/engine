<?php

declare(strict_types=1);

/**
 * @var string $siteTitle
 * @var string $entryTitle
 * @var string $content
 * @var string $date
 * @var string $author
 * @var string $collection
 * @var ?Navigation $nav
 */

use App\Content\Model\Navigation;

?>
<div class="minimal-layout"><h1><?= htmlspecialchars($entryTitle) ?></h1><?= $content ?></div>
