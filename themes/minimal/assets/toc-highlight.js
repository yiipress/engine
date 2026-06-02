(function () {
    'use strict';

    function initTocHighlight() {
        var links = Array.prototype.slice.call(document.querySelectorAll('.toc-sidebar a[href^="#"]'));
        var items = [];
        var activeItem = null;

        links.forEach(function (link) {
            var id = decodeURIComponent(link.getAttribute('href').slice(1));
            var heading = document.getElementById(id);

            if (heading) {
                items.push({ heading: heading, link: link, listItem: link.closest('li') });
            }
        });

        if (items.length === 0) {
            return;
        }

        function setActive(item) {
            if (activeItem === item) {
                return;
            }

            if (activeItem) {
                activeItem.listItem.classList.remove('is-current');
                activeItem.link.removeAttribute('aria-current');
            }

            activeItem = item;
            activeItem.listItem.classList.add('is-current');
            activeItem.link.setAttribute('aria-current', 'true');
        }

        function update() {
            var active = items[0];
            var offset = 96;

            items.forEach(function (item) {
                if (item.heading.getBoundingClientRect().top <= offset) {
                    active = item;
                }
            });

            setActive(active);
        }

        update();
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTocHighlight, { once: true });
    } else {
        initTocHighlight();
    }
})();
