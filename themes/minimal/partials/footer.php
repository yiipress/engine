<?php

use YiiPress\Content\Model\Navigation;
use YiiPress\Render\NavigationRenderer;

/**
 * @var ?Navigation $nav
 * @var string $rootPath
 * @var string $uiLanguage
 * @var Closure(string, int, ?string, bool): string $h
 * @var Closure(string): string $themeAsset
 */
$uiLanguage ??= 'en';
?>
<?php if ($nav !== null && $nav->menu('footer') !== []): ?>
<footer class="site-footer">
    <div class="container">
        <?= NavigationRenderer::render($nav, 'footer', $rootPath, $uiLanguage, $uiLanguage) ?>
        <script>
            (function () {
                if (typeof window.__yiipressApplyMenuTranslations !== 'function') {
                    return;
                }

                window.__yiipressApplyMenuTranslations(document.currentScript.previousElementSibling);
            })();
        </script>
    </div>
</footer>
<?php endif; ?>
<script src="<?= $h($themeAsset('dark-mode.js')) ?>"></script>
