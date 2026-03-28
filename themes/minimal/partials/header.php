<?php

use App\Content\Model\Navigation;
use App\Render\NavigationRenderer;

/**
 * @var string $siteTitle
 * @var ?Navigation $nav
 * @var string $rootPath
 * @var bool $search
 * @var int $searchResults
 */
$search ??= false;
$searchResults ??= 10;
?>
<header class="site-header">
    <div class="container">
        <a class="site-name" href="<?= $rootPath ?>"><?= htmlspecialchars($siteTitle) ?></a>
        <?php if ($nav !== null && $nav->menu('main') !== []): ?>
            <?= NavigationRenderer::render($nav, 'main', $rootPath) ?>
        <?php endif; ?>

        <div class="buttons">
            <?php if ($search): ?>
                <button id="search-button" class="icon-btn" type="button" aria-label="Search" title="Search (Ctrl+K)">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                </button>
            <?php endif; ?>
            <button id="theme-toggle" class="icon-btn" type="button" aria-label="Toggle dark mode"></button>
        </div>
    </div>
</header>
<?php if ($search): ?>
<div id="search-overlay" hidden></div>
<div id="search-modal" role="dialog" aria-modal="true" aria-label="Search" hidden>
    <div id="search-input-wrapper">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <input type="search" id="search-input" placeholder="Search…" autocomplete="off"
               data-root="<?= htmlspecialchars($rootPath) ?>"
               data-max-results="<?= $searchResults ?>">
        <span class="search-hint">ESC</span>
    </div>
    <ul id="search-results" role="listbox" aria-label="Search results"></ul>
</div>
<?php endif; ?>
