(function () {
    'use strict';

    function activate(tab, focus) {
        const group = tab.closest('[data-code-group]');
        const tabs = Array.from(group.querySelectorAll('[role="tab"]'));

        tabs.forEach(function (candidate) {
            const selected = candidate === tab;
            const panel = document.getElementById(candidate.getAttribute('aria-controls'));

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
        group.classList.add('is-enhanced');
        activate(group.querySelector('[role="tab"]'), false);
    });
}());
