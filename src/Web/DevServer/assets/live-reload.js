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
        es.addEventListener("build-error", function(event) {
            es.close();
            try {
                var payload = JSON.parse(event.data);
                if (payload.output) {
                    console.error(payload.output);
                }
            } catch (error) {
                console.error(event.data);
            }
        });
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
