<?php
/**
 * @var string $title
 * @var string $rootPath
 * @var string $headAssets
 * @var string|null $collectionName
 * @var MetaTags|null $metaTags
 * @var bool $search
 * @var int $searchResults
 * @var AssetFingerprintManifest|null $assetManifest
 * @var string $uiLanguage
 * @var list<string> $uiLanguages
 * @var array<string, array<string, string>> $uiCatalogs
 */

use App\Build\AssetFingerprintManifest;
use App\Build\Asset;
use App\Build\MetaTags;
use App\I18n\UiText;

$headAssets ??= '';
$collectionName ??= null;
$metaTags ??= null;
$search ??= false;
$searchResults ??= 10;
$uiLanguage ??= 'en';
$uiLanguages ??= [$uiLanguage];
$uiCatalogs ??= [$uiLanguage => []];
$ui ??= UiText::for($uiLanguage);
$t ??= static fn (string $key, array $params = []): string => $ui->get($key, $params);
?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?></title>
<?php if ($metaTags !== null): ?>
<?php if ($metaTags->canonicalUrl !== ''): ?>
    <link rel="canonical" href="<?= htmlspecialchars($metaTags->canonicalUrl) ?>">
<?php endif; ?>
    <meta name="description" content="<?= htmlspecialchars($metaTags->description) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($metaTags->title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($metaTags->description) ?>">
    <meta property="og:type" content="<?= htmlspecialchars($metaTags->type) ?>">
<?php if ($metaTags->canonicalUrl !== ''): ?>
    <meta property="og:url" content="<?= htmlspecialchars($metaTags->canonicalUrl) ?>">
<?php endif; ?>
<?php if ($metaTags->image !== ''): ?>
    <meta property="og:image" content="<?= htmlspecialchars($metaTags->image) ?>">
<?php endif; ?>
    <meta name="twitter:card" content="<?= htmlspecialchars($metaTags->twitterCard) ?>">
<?php if ($metaTags->twitterSite !== ''): ?>
    <meta name="twitter:site" content="<?= htmlspecialchars($metaTags->twitterSite) ?>">
<?php endif; ?>
<?php foreach ($metaTags->alternateLanguages as $hreflang => $url): ?>
    <link rel="alternate" hreflang="<?= htmlspecialchars($hreflang) ?>" href="<?= htmlspecialchars($url) ?>">
<?php endforeach; ?>
<?php endif; ?>
    <script>
        (function () {
            var root = document.documentElement;
            var theme = 'light';
            var defaultUiLanguage = <?= json_encode($uiLanguage, JSON_THROW_ON_ERROR) ?>;
            var uiLanguage = <?= json_encode($uiLanguage, JSON_THROW_ON_ERROR) ?>;
            var uiLanguages = <?= json_encode(array_values($uiLanguages), JSON_THROW_ON_ERROR) ?>;

            try {
                theme = localStorage.getItem('yiipress-theme')
                    || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                uiLanguage = localStorage.getItem('yiipress-ui-language') || uiLanguage;
            } catch (e) {
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    theme = 'dark';
                }
            }

            if (uiLanguages.indexOf(uiLanguage) === -1) {
                uiLanguage = uiLanguages[0] || uiLanguage;
            }

            window.__yiipressUiLanguage = uiLanguage;
            window.__yiipressDefaultUiLanguage = defaultUiLanguage;
            window.__yiipressNormalizeLanguage = function (language) {
                return String(language || 'en').toLowerCase().replace('_', '-').split('-')[0] || 'en';
            };
            window.__yiipressResolveMenuTitle = function (titles, language, fallback) {
                var order;
                var i;
                var firstLanguage;

                if (!titles || typeof titles !== 'object') {
                    return fallback;
                }

                order = [
                    window.__yiipressNormalizeLanguage(language),
                    window.__yiipressNormalizeLanguage(defaultUiLanguage),
                    'en',
                ].filter(function (value, index, values) {
                    return value && values.indexOf(value) === index;
                });

                for (i = 0; i < order.length; i++) {
                    if (typeof titles[order[i]] === 'string' && titles[order[i]] !== '') {
                        return titles[order[i]];
                    }
                }

                firstLanguage = Object.keys(titles)[0];

                return firstLanguage && typeof titles[firstLanguage] === 'string' && titles[firstLanguage] !== ''
                    ? titles[firstLanguage]
                    : fallback;
            };
            window.__yiipressCapitalizeFirst = function (value, language) {
                var characters = Array.from(value);

                if (characters.length === 0) {
                    return value;
                }

                return characters[0].toLocaleUpperCase(language) + characters.slice(1).join('');
            };
            window.__yiipressGetLanguageName = function (language) {
                try {
                    if (typeof Intl.DisplayNames === 'function') {
                        return window.__yiipressCapitalizeFirst(
                            new Intl.DisplayNames([language], { type: 'language' }).of(language) || language.toUpperCase(),
                            language,
                        );
                    }
                } catch (e) {
                }

                return language.toUpperCase();
            };
            window.__yiipressApplyLanguageSelector = function (selector) {
                var activeLanguage;

                if (!selector) {
                    return;
                }

                activeLanguage = root.getAttribute('data-ui-language') || uiLanguage;
                if (uiLanguages.indexOf(activeLanguage) === -1) {
                    activeLanguage = uiLanguages[0] || activeLanguage;
                }

                selector.value = activeLanguage;
                Array.prototype.forEach.call(selector.options, function (option) {
                    option.textContent = window.__yiipressGetLanguageName(option.value);
                });
            };
            window.__yiipressApplyMenuTranslations = function (container) {
                if (!container) {
                    return;
                }

                container.querySelectorAll('[data-ui-menu-title]').forEach(function (element) {
                    var titles;

                    try {
                        titles = JSON.parse(element.getAttribute('data-ui-menu-title') || 'null');
                    } catch (e) {
                        titles = null;
                    }

                    element.textContent = window.__yiipressResolveMenuTitle(
                        titles,
                        root.getAttribute('data-ui-language') || uiLanguage,
                        element.getAttribute('data-ui-menu-default') || '',
                    );
                });
            };

            root.setAttribute('data-theme', theme);
            root.setAttribute('data-ui-language', uiLanguage);
            root.style.colorScheme = theme;
        })();
    </script>
    <script id="yiipress-ui-catalogs" type="application/json" data-default-language="<?= htmlspecialchars($uiLanguage) ?>"><?= json_encode($uiCatalogs, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <link rel="stylesheet" href="<?= htmlspecialchars(Asset::url('assets/theme/style.css', $rootPath, $assetManifest)) ?>">
<?php if ($collectionName !== null): ?>
    <link rel="alternate" type="application/rss+xml" title="<?= htmlspecialchars($t('rss_feed')) ?>" data-ui-attr-title="rss_feed" href="<?= $rootPath . htmlspecialchars($collectionName) ?>/rss.xml">
    <link rel="alternate" type="application/atom+xml" title="<?= htmlspecialchars($t('atom_feed')) ?>" data-ui-attr-title="atom_feed" href="<?= $rootPath . htmlspecialchars($collectionName) ?>/feed.xml">
<?php endif; ?>
    <script src="<?= htmlspecialchars(Asset::url('assets/theme/image-zoom.js', $rootPath, $assetManifest)) ?>" defer></script>
    <script src="<?= htmlspecialchars(Asset::url('assets/theme/ui-language.js', $rootPath, $assetManifest)) ?>" defer></script>
<?php if ($search): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(Asset::url('assets/theme/search.css', $rootPath, $assetManifest)) ?>">
    <script src="<?= htmlspecialchars(Asset::url('assets/theme/search.js', $rootPath, $assetManifest)) ?>" defer></script>
<?php endif; ?>
<?= $headAssets ?>
