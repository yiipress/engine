# Web application

The web application serves the built site for local development. In the Docker development container, `make up` starts the ReactPHP preview server with `yii serve 0.0.0.0 --port=19777`. Live reload requires PHP `ext-inotify`; the Docker images install it by default.

Source, PHAR, and static binary execution all use the same `serve` command. They resolve `content/` and `output/` from the current working directory by default, and custom paths can be passed with `--content-dir` and `--output-dir`. The server uses ReactPHP stream sockets with preforked worker processes, serves built files from the output directory directly in the server loop, and handles the live reload SSE endpoint there too. Each worker keeps one shared inotify watcher for all live reload clients, so fast navigation does not repeat recursive watch setup. Idle live reload connections and static file responses do not occupy Yii request workers. EventSource clients close during page navigation, so stale browser connections are cleaned up without periodically dropping active live reload listeners. It does not require PHP's native `sockets` extension.

## Static file serving

The web application serves files from the configured output directory. Requests for `/foo/` serve `<output-dir>/foo/index.html`, requests for `/style.css` serve `<output-dir>/style.css`, etc. `serve` follows these resolution rules in its ReactPHP server loop, injects the live reload script into served HTML directly, and streams non-HTML files so large images, fonts, and media do not have to be buffered into memory before being sent.

If the output directory is missing or empty, a build runs automatically on the first request.

## Live reload

When content files or templates change, the browser refreshes with the updated build.

How it works:

1. `LiveReloadMiddleware` injects a small JavaScript snippet before `</body>` in every HTML response.
2. The snippet opens an SSE (Server-Sent Events) connection to `/_live-reload`.
3. The ReactPHP server handles this endpoint directly and attaches all EventSource clients in the same worker to a shared inotify read stream.
4. When a change is detected, `SiteBuildRunner` triggers a normal `yii build`, so live reload benefits from the same incremental build pipeline as manual builds.
5. After the build completes, the server sends a `reload` event and the browser refreshes.
