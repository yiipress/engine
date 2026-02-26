(function(){
    const k = 'yiipress-theme';
    const b = document.documentElement;
    const t = document.querySelector('.theme-toggle');

    function set(v) {
        b.setAttribute('data-theme', v);
        localStorage.setItem(k, v);
        if (t) t.textContent = v === 'dark' ? '\u2600' : '\u263E';
    }

    const s = localStorage.getItem(k) || (matchMedia('(prefers-color-scheme:dark)').matches ? 'dark' : 'light');
    set(s);

    if (t) {
        t.addEventListener('click', function(){
            const newTheme = b.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            set(newTheme);
        });
    }
})();
