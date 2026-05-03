<?php

declare(strict_types=1);

/**
 * @var string $siteTitle
 * @var string $entryTitle
 * @var string $content
 * @var string $date
 * @var string $author
 * @var string $collection
 * @var string $language
 * @var ?Navigation $nav
 * @var YiiPress\I18n\UiText $ui
 * @var Closure(string, int, ?string, bool): string $h
 * @var Closure(string, array): string $t
 */

use YiiPress\Content\Model\Navigation;

?>
<div class="minimal-layout"><h1><?= $h($entryTitle) ?></h1><?= $content ?></div>
