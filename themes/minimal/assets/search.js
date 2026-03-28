(function () {
    'use strict';

    const modal = document.getElementById('search-modal');
    const input = document.getElementById('search-input');
    const resultsList = document.getElementById('search-results');
    const button = document.getElementById('search-button');
    const overlay = document.getElementById('search-overlay');

    if (!modal || !input || !resultsList || !button || !overlay) return;

    const maxResults = parseInt(input.dataset.maxResults || '10', 10);
    const root = input.dataset.root || '/';

    let index = null;
    let loading = false;
    let debounceTimer;

    function open() {
        modal.removeAttribute('hidden');
        overlay.removeAttribute('hidden');
        input.focus();
    }

    function close() {
        modal.setAttribute('hidden', '');
        overlay.setAttribute('hidden', '');
        input.value = '';
        resultsList.innerHTML = '';
    }

    button.addEventListener('click', function () {
        open();
        loadIndex();
    });

    overlay.addEventListener('click', close);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hasAttribute('hidden')) {
            close();
            return;
        }
        if ((e.key === 'k' || e.key === 'K') && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            if (modal.hasAttribute('hidden')) {
                open();
                loadIndex();
            } else {
                close();
            }
        }
    });

    input.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(doSearch, 150);
    });

    input.addEventListener('keydown', function (e) {
        const items = resultsList.querySelectorAll('[role="option"]');
        if (items.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const active = resultsList.querySelector('[aria-selected="true"]');
            const next = active ? active.nextElementSibling : items[0];
            if (next) {
                if (active) active.removeAttribute('aria-selected');
                next.setAttribute('aria-selected', 'true');
                next.querySelector('a').focus();
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const active = resultsList.querySelector('[aria-selected="true"]');
            if (active) {
                active.removeAttribute('aria-selected');
                const prev = active.previousElementSibling;
                if (prev) {
                    prev.setAttribute('aria-selected', 'true');
                    prev.querySelector('a').focus();
                } else {
                    input.focus();
                }
            }
        }
    });

    resultsList.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
            input.dispatchEvent(new KeyboardEvent('keydown', { key: e.key, bubbles: false }));
            e.preventDefault();
        }
    });

    function loadIndex() {
        if (index !== null || loading) return;
        loading = true;
        fetch(root + 'search-index.json')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                index = data;
                loading = false;
                if (input.value.trim()) doSearch();
            })
            .catch(function () {
                loading = false;
            });
    }

    function doSearch() {
        const q = input.value.trim();
        if (!q || !index) {
            resultsList.innerHTML = '';
            return;
        }

        const ql = q.toLowerCase();
        const scored = [];

        for (let i = 0; i < index.length; i++) {
            const s = scoreItem(index[i], ql);
            if (s > 0) scored.push({ item: index[i], score: s });
        }

        scored.sort(function (a, b) { return b.score - a.score; });
        const top = scored.slice(0, maxResults);

        if (top.length === 0) {
            resultsList.innerHTML = '<li class="search-no-results">No results found.</li>';
            return;
        }

        resultsList.innerHTML = top.map(function (r) {
            const item = r.item;
            const summary = item.summary ? escHtml(item.summary.substring(0, 120)) : '';
            return '<li role="option" tabindex="-1">' +
                '<a href="' + escHtml(item.url) + '">' +
                '<span class="search-result-title">' + escHtml(item.title) + '</span>' +
                (summary ? '<span class="search-result-summary">' + summary + '</span>' : '') +
                '</a></li>';
        }).join('');
    }

    function scoreItem(item, q) {
        const title = item.title.toLowerCase();
        const summary = (item.summary || '').toLowerCase();
        const body = (item.body || '').toLowerCase();
        const tags = (item.tags || []).map(function (t) { return t.toLowerCase(); });

        let score = 0;

        if (title.includes(q)) score += 10;
        if (tags.some(function (t) { return t.includes(q); })) score += 6;
        if (summary && summary.includes(q)) score += 4;
        if (body && body.includes(q)) score += 2;

        if (score === 0) {
            if (fuzzy(title, q)) score += 5;
            if (tags.some(function (t) { return fuzzy(t, q); })) score += 3;
            if (summary && fuzzy(summary, q)) score += 2;
            if (body && fuzzy(body, q)) score += 1;
        }

        return score;
    }

    function fuzzy(text, q) {
        var qi = 0;
        for (var i = 0; i < text.length && qi < q.length; i++) {
            if (text[i] === q[qi]) qi++;
        }
        return qi === q.length;
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
}());
