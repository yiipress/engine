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
            
            // Re-render Mermaid diagrams on theme change
            if (typeof mermaid !== 'undefined') {
                mermaid.initialize({
                    startOnLoad: false,
                    theme: newTheme === 'dark' ? 'dark' : 'default',
                    securityLevel: 'loose'
                });
                mermaid.run({
                    querySelector: '.mermaid',
                    postRenderCallback: function(id) {
                        const el = document.querySelector('.mermaid[data-mermaid-id="' + id + '"]');
                        if (el) {
                            el.removeAttribute('data-processed');
                        }
                    }
                });
            }
        });
    }
})();
