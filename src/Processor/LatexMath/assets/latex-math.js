(function () {
    function renderEquation(element) {
        var displayMode = element.classList.contains('display');
        var replacement = document.createElement(displayMode ? 'div' : 'span');
        var source = element.textContent || '';

        replacement.className = displayMode ? 'math math-display' : 'math math-inline';

        if (!window.katex || typeof window.katex.render !== 'function') {
            replacement.textContent = source;
            element.replaceWith(replacement);
            return;
        }

        try {
            window.katex.render(source, replacement, {
                displayMode: displayMode,
                throwOnError: false
            });
        } catch (e) {
            replacement.textContent = source;
        }

        element.replaceWith(replacement);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('span.math').forEach(renderEquation);
    });
})();
