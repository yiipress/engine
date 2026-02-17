(function(){
    var k = 'yiipress-theme', b = document.documentElement, t = document.querySelector('.theme-toggle');
    function set(v) { b.setAttribute('data-theme', v); localStorage.setItem(k, v); t && (t.textContent = v === 'dark' ? '\u2600' : '\u263E'); }
    var s = localStorage.getItem(k) || (matchMedia('(prefers-color-scheme:dark)').matches ? 'dark' : 'light');
    set(s);
    t && t.addEventListener('click', function(){ set(b.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'); });
})();
