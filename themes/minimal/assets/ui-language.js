(function () {
    'use strict';

    const storageKey = 'yiipress-ui-language';
    const root = document.documentElement;
    const selector = document.getElementById('ui-language-selector');
    const catalogsNode = document.getElementById('yiipress-ui-catalogs');

    if (!catalogsNode) {
        return;
    }

    let catalogs = {};
    try {
        catalogs = JSON.parse(catalogsNode.textContent || '{}');
    } catch (e) {
        return;
    }

    const defaultLanguage = catalogsNode.dataset.defaultLanguage || 'en';
    const availableLanguages = selector
        ? Array.prototype.map.call(selector.options, function (option) { return option.value; })
        : Object.keys(catalogs);

    function translate(key, params, language) {
        const order = unique([language, defaultLanguage, 'en']);
        let message = key;

        for (let i = 0; i < order.length; i++) {
            const catalog = catalogs[order[i]] || null;
            if (catalog && Object.prototype.hasOwnProperty.call(catalog, key)) {
                message = catalog[key];
                break;
            }
        }

        if (!params) {
            return message;
        }

        return Object.keys(params).reduce(function (result, name) {
            return result.replaceAll('{' + name + '}', String(params[name]));
        }, message);
    }

    function translateMenuTitle(titles, language, fallback) {
        if (!titles || typeof titles !== 'object') {
            return fallback;
        }

        const order = unique([
            normalizeLanguage(language),
            normalizeLanguage(defaultLanguage),
            'en',
        ]);

        for (let i = 0; i < order.length; i++) {
            const title = titles[order[i]];
            if (typeof title === 'string' && title !== '') {
                return title;
            }
        }

        const firstLanguage = Object.keys(titles)[0];
        if (firstLanguage && typeof titles[firstLanguage] === 'string' && titles[firstLanguage] !== '') {
            return titles[firstLanguage];
        }

        return fallback;
    }

    function getLanguageName(language) {
        try {
            if (typeof Intl.DisplayNames === 'function') {
                const displayNames = new Intl.DisplayNames([language], { type: 'language' });
                const label = displayNames.of(language);
                if (label) {
                    return capitalizeFirst(label, language);
                }
            }
        } catch (e) {
        }

        return language.toUpperCase();
    }

    function monthName(month, uiLanguage) {
        try {
            const value = new Intl.DateTimeFormat([uiLanguage, defaultLanguage, 'en'], {
                month: 'long',
                timeZone: 'UTC',
            }).format(new Date(Date.UTC(2000, month - 1, 1)));
            return capitalizeFirst(value, uiLanguage);
        } catch (e) {
            return String(month);
        }
    }

    function capitalizeFirst(value, language) {
        const characters = Array.from(value);
        if (characters.length === 0) {
            return value;
        }

        return characters[0].toLocaleUpperCase(language) + characters.slice(1).join('');
    }

    function normalizeLanguage(language) {
        return String(language || 'en').toLowerCase().replace('_', '-').split('-')[0] || 'en';
    }

    function parseParams(value) {
        if (!value) {
            return null;
        }

        try {
            return JSON.parse(value);
        } catch (e) {
            return null;
        }
    }

    function applyTranslations(uiLanguage) {
        root.setAttribute('data-ui-language', uiLanguage);

        document.querySelectorAll('[data-ui-key]').forEach(function (element) {
            element.textContent = translate(
                element.getAttribute('data-ui-key'),
                parseParams(element.getAttribute('data-ui-params')),
                uiLanguage,
            );
        });

        document.querySelectorAll('[data-ui-month]').forEach(function (element) {
            const month = parseInt(element.getAttribute('data-ui-month') || '', 10);
            if (!Number.isNaN(month) && month >= 1 && month <= 12) {
                element.textContent = monthName(month, uiLanguage);
            }
        });

        document.querySelectorAll('[data-ui-menu-title]').forEach(function (element) {
            element.textContent = translateMenuTitle(
                parseParams(element.getAttribute('data-ui-menu-title')),
                uiLanguage,
                element.getAttribute('data-ui-menu-default') || '',
            );
        });

        document.querySelectorAll('*').forEach(function (element) {
            Array.prototype.forEach.call(element.attributes, function (attribute) {
                if (!attribute.name.startsWith('data-ui-attr-')) {
                    return;
                }

                const targetAttribute = attribute.name.substring('data-ui-attr-'.length);
                const paramsAttribute = 'data-ui-attr-params-' + targetAttribute;
                element.setAttribute(
                    targetAttribute,
                    translate(attribute.value, parseParams(element.getAttribute(paramsAttribute)), uiLanguage),
                );
            });
        });

        if (selector) {
            selector.value = uiLanguage;
            Array.prototype.forEach.call(selector.options, function (option) {
                option.textContent = getLanguageName(option.value);
            });
        }

        document.dispatchEvent(new CustomEvent('yiipress:ui-language-change', {
            detail: { language: uiLanguage },
        }));
    }

    function setLanguage(uiLanguage) {
        if (availableLanguages.indexOf(uiLanguage) === -1) {
            uiLanguage = defaultLanguage;
        }

        try {
            localStorage.setItem(storageKey, uiLanguage);
        } catch (e) {
        }

        applyTranslations(uiLanguage);
    }

    function unique(values) {
        return values.filter(function (value, index) {
            return values.indexOf(value) === index && value;
        });
    }

    let initialLanguage = root.getAttribute('data-ui-language') || defaultLanguage;
    if (availableLanguages.indexOf(initialLanguage) === -1) {
        initialLanguage = defaultLanguage;
    }

    applyTranslations(initialLanguage);

    if (selector) {
        selector.addEventListener('change', function () {
            setLanguage(selector.value);
        });
    }
}());
