(function () {
    'use strict';

    function activate(tab, focus) {
        if (!tab) {
            return;
        }

        const group = tab.closest('[data-code-group]');
        if (!group) {
            return;
        }

        const tabs = Array.from(group.querySelectorAll('[role="tab"]'));

        tabs.forEach(function (candidate) {
            const selected = candidate === tab;
            const panel = group.querySelector('#' + candidate.getAttribute('aria-controls'));

            candidate.setAttribute('aria-selected', selected ? 'true' : 'false');
            candidate.tabIndex = selected ? 0 : -1;
            if (panel) {
                panel.hidden = !selected;
            }
        });

        if (focus) {
            tab.focus();
            tab.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
        }

        group.dispatchEvent(new CustomEvent('yiipress:code-group-activate', {
            bubbles: true,
            detail: { tab: tab },
        }));
    }

    document.addEventListener('click', function (event) {
        const tab = event.target.closest('[data-code-group] [role="tab"]');
        if (tab) {
            activate(tab, false);
        }
    });

    document.addEventListener('keydown', function (event) {
        const tab = event.target.closest('[data-code-group] [role="tab"]');
        if (!tab || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
            return;
        }

        const tabs = Array.from(tab.closest('[role="tablist"]').querySelectorAll('[role="tab"]'));
        let index = tabs.indexOf(tab);
        event.preventDefault();

        if (event.key === 'Home') {
            index = 0;
        } else if (event.key === 'End') {
            index = tabs.length - 1;
        } else {
            index = (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
        }
        activate(tabs[index], true);
    });

    document.querySelectorAll('[data-code-group]').forEach(function (group) {
        const panels = Array.from(group.querySelectorAll('[data-code-group-panel]'));
        if (panels.length < 2) {
            return;
        }

        const tablist = document.createElement('div');
        tablist.className = 'code-group-tabs';
        tablist.setAttribute('role', 'tablist');

        panels.forEach(function (panel, index) {
            const tab = document.createElement('button');
            const tabId = panel.id + '-tab';

            tab.type = 'button';
            tab.id = tabId;
            tab.textContent = panel.dataset.label;
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-controls', panel.id);
            tab.setAttribute('aria-selected', index === 0 ? 'true' : 'false');
            tab.tabIndex = index === 0 ? 0 : -1;
            panel.setAttribute('role', 'tabpanel');
            panel.setAttribute('aria-labelledby', tabId);
            tablist.appendChild(tab);
        });

        group.prepend(tablist);
        group.classList.add('is-enhanced');
        activate(group.querySelector('[role="tab"]'), false);
    });
}());
