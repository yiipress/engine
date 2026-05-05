(function(){
    var leaving = false;
    var reconnectTimer = 0;
    var es = null;

    function close() {
        leaving = true;
        if (reconnectTimer) {
            clearTimeout(reconnectTimer);
        }
        if (es) {
            es.close();
        }
    }

    function connect() {
        if (leaving) {
            return;
        }
        es = new EventSource("/_live-reload");
        es.addEventListener("reload", function() { es.close(); location.reload(); });
        es.addEventListener("ping", function() {});
        es.onerror = function() {
            es.close();
            if (!leaving) {
                reconnectTimer = setTimeout(connect, 2000);
            }
        };
    }

    window.addEventListener("pagehide", close);
    window.addEventListener("beforeunload", close);
    connect();
})();
