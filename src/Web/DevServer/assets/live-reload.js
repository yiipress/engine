(function(){
    var leaving = false;
    var reconnectTimer = 0;
    var es = null;
    var errorPanel = null;
    var errorOutput = null;

    function hideBuildError() {
        if (errorPanel) {
            errorPanel.remove();
            errorPanel = null;
            errorOutput = null;
        }
    }

    function showBuildError(output) {
        if (!errorPanel) {
            errorPanel = document.createElement("section");
            errorPanel.id = "yiipress-build-error";
            errorPanel.setAttribute("role", "status");
            errorPanel.style.cssText = "position:fixed;top:16px;right:16px;z-index:2147483647;box-sizing:border-box;width:min(720px,calc(100vw - 32px));max-height:min(520px,calc(100vh - 32px));overflow:hidden;border:1px solid rgba(185,28,28,.38);border-radius:8px;background:#1f1515;color:#fff;box-shadow:0 18px 48px rgba(0,0,0,.32);font:13px/1.45 ui-monospace,SFMono-Regular,Menlo,Consolas,Liberation Mono,monospace";

            var header = document.createElement("div");
            header.style.cssText = "display:flex;align-items:center;gap:12px;padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.12);background:#3f1717";

            var title = document.createElement("strong");
            title.textContent = "Build failed";
            title.style.cssText = "flex:1;font:600 13px/1.3 system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif";

            var closeButton = document.createElement("button");
            closeButton.type = "button";
            closeButton.textContent = "Close";
            closeButton.style.cssText = "border:1px solid rgba(255,255,255,.2);border-radius:6px;background:rgba(255,255,255,.08);color:#fff;padding:4px 8px;font:12px system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;cursor:pointer";
            closeButton.addEventListener("click", hideBuildError);

            errorOutput = document.createElement("pre");
            errorOutput.style.cssText = "box-sizing:border-box;max-height:min(454px,calc(100vh - 98px));margin:0;padding:12px;overflow:auto;white-space:pre-wrap;word-break:break-word";

            header.appendChild(title);
            header.appendChild(closeButton);
            errorPanel.appendChild(header);
            errorPanel.appendChild(errorOutput);
            document.body.appendChild(errorPanel);
        }

        errorOutput.textContent = output || "Build failed.";
    }

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
        es.addEventListener("reload", function() { hideBuildError(); es.close(); location.reload(); });
        es.addEventListener("build-error", function(event) {
            var output = event.data;
            try {
                var payload = JSON.parse(event.data);
                if (payload.output) {
                    output = payload.output;
                }
            } catch (error) {}
            showBuildError(output);
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
