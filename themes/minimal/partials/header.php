<?php

use App\Content\Model\Navigation;
use App\Render\NavigationRenderer;

/**
 * @var string $siteTitle
 * @var ?Navigation $nav
 * @var string $rootPath
 * @var bool $search
 * @var int $searchResults
 * @var string $uiLanguage
 * @var list<string> $uiLanguages
 * @var Closure(string, array): string $t
 * @var Closure(string): string $capitalizeUtf8
 * @var Closure(string): string $languageName
 */
$search ??= false;
$searchResults ??= 10;
$uiLanguage ??= 'en';
$uiLanguages ??= [$uiLanguage];
$capitalizeUtf8 ??= static function (string $value): string {
    $firstCharacter = mb_substr($value, 0, 1, 'UTF-8');
    if ($firstCharacter === '') {
        return $value;
    }

    return mb_strtoupper($firstCharacter, 'UTF-8') . mb_substr($value, 1, null, 'UTF-8');
};
$languageName ??= static fn (string $language): string => $capitalizeUtf8(\Locale::getDisplayLanguage($language, $language) ?: strtoupper($language));
?>
<header class="site-header">
    <div class="container">
        <a class="site-name" href="<?= $rootPath ?>"><?= htmlspecialchars($siteTitle) ?></a>
        <?php if ($nav !== null && $nav->menu('main') !== []): ?>
            <?= NavigationRenderer::render($nav, 'main', $rootPath, $uiLanguage, $uiLanguage) ?>
            <script>
                (function () {
                    if (typeof window.__yiipressApplyMenuTranslations !== 'function') {
                        return;
                    }

                    window.__yiipressApplyMenuTranslations(document.currentScript.previousElementSibling);
                })();
            </script>
        <?php endif; ?>

        <div class="buttons">
            <?php if (count($uiLanguages) > 1): ?>
                <label class="ui-language-control">
                    <span class="sr-only" data-ui-key="ui_language"><?= htmlspecialchars($t('ui_language')) ?></span>
                    <select id="ui-language-selector" class="ui-language-selector" aria-label="<?= htmlspecialchars($t('ui_language')) ?>" data-ui-attr-aria-label="ui_language">
<?php foreach ($uiLanguages as $availableLanguage): ?>
                        <option value="<?= htmlspecialchars($availableLanguage) ?>"<?= $availableLanguage === $uiLanguage ? ' selected' : '' ?>><?= htmlspecialchars($languageName($availableLanguage)) ?></option>
<?php endforeach; ?>
                    </select>
                    <script>
                        (function () {
                            if (typeof window.__yiipressApplyLanguageSelector !== 'function') {
                                return;
                            }

                            window.__yiipressApplyLanguageSelector(document.currentScript.previousElementSibling);
                        })();
                    </script>
                </label>
            <?php endif; ?>
            <?php if ($search): ?>
                <button id="search-button" class="icon-btn" type="button" aria-label="<?= htmlspecialchars($t('search')) ?>" data-ui-attr-aria-label="search" title="<?= htmlspecialchars($t('search_button_title')) ?>" data-ui-attr-title="search_button_title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                </button>
            <?php endif; ?>
            <button id="theme-toggle" class="icon-btn" type="button" aria-label="<?= htmlspecialchars($t('toggle_dark_mode')) ?>" data-ui-attr-aria-label="toggle_dark_mode"></button>
        </div>
    </div>
</header>
<?php if ($search): ?>
<div id="search-overlay" hidden></div>
<div id="search-modal" role="dialog" aria-modal="true" aria-label="<?= htmlspecialchars($t('search')) ?>" data-ui-attr-aria-label="search" hidden>
    <div id="search-input-wrapper">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <input type="search" id="search-input" placeholder="<?= htmlspecialchars($t('search_placeholder')) ?>" data-ui-attr-placeholder="search_placeholder" autocomplete="off"
               data-root="<?= htmlspecialchars($rootPath) ?>"
               data-max-results="<?= $searchResults ?>">
        <span class="search-hint">ESC</span>
    </div>
    <ul id="search-results" role="listbox" aria-label="<?= htmlspecialchars($t('search_results')) ?>" data-ui-attr-aria-label="search_results" data-no-results-text="<?= htmlspecialchars($t('search_no_results')) ?>" data-ui-attr-data-no-results-text="search_no_results"></ul>
</div>
<?php endif; ?>
