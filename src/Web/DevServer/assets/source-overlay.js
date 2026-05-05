(function(){
    function addSourceButton() {
        if (document.getElementById("yiipress-open-source")) {
            return;
        }

        var button = document.createElement("button");
        button.id = "yiipress-open-source";
        button.type = "button";
        button.textContent = "✏️";
        button.title = "Open Markdown source";
        button.setAttribute("aria-label", "Open Markdown source");
        button.style.cssText = "position:fixed;right:16px;bottom:16px;z-index:2147483647;width:44px;height:44px;padding:0;border:0;border-radius:50%;background:rgba(17,17,17,.68);color:#fff;font:20px/44px system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;box-shadow:0 4px 14px rgba(0,0,0,.25);cursor:pointer;text-align:center;transition:transform .14s ease,background .14s ease;transform-origin:center";
        button.addEventListener("mouseenter", function() {
            button.style.transform = "scale(1.12)";
            button.style.background = "rgba(17,17,17,.78)";
        });
        button.addEventListener("mouseleave", function() {
            button.style.transform = "";
            button.style.background = "rgba(17,17,17,.68)";
        });
        button.addEventListener("focus", function() {
            button.style.transform = "scale(1.12)";
            button.style.background = "rgba(17,17,17,.78)";
        });
        button.addEventListener("blur", function() {
            button.style.transform = "";
            button.style.background = "rgba(17,17,17,.68)";
        });
        button.addEventListener("click", function() {
            button.disabled = true;
            fetch("/_open-source", {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify({path: window.location.pathname})
            }).then(function(response) {
                if (!response.ok) {
                    throw new Error("HTTP " + response.status);
                }
            }).catch(function(error) {
                console.warn("YiiPress could not open the Markdown source file.", error);
            }).finally(function() {
                button.disabled = false;
            });
        });

        document.body.appendChild(button);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", addSourceButton, {once: true});
    } else {
        addSourceButton();
    }
})();
