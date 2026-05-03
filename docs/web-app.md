# Web application

The web application serves the built site for local development. In the Docker development container, it runs on PHP's built-in server through Yii's `yii serve` command. `make up` starts the Docker development container with `yii serve 0.0.0.0 --port=8080`. Live reload requires PHP `ext-inotify`; the Docker images install it by default.

The packaged PHAR and static binary keep the same `serve` command, but they do not require a `public/` directory beside the binary. They resolve `content/` and `output/` from the current working directory. The packaged server uses ReactPHP stream sockets with preforked worker processes, serves built files from `output/` directly in the server loop, and handles the live reload SSE endpoint there too. Each worker keeps one shared inotify watcher for all live reload clients, so fast navigation does not repeat recursive watch setup. Idle live reload connections and static file responses do not occupy Yii request workers. EventSource clients close during page navigation, so stale browser connections are cleaned up without periodically dropping active live reload listeners. It does not require PHP's native `sockets` extension.

## Static file serving

The web application serves files from the `output/` directory. Requests for `/foo/` serve `output/foo/index.html`, requests for `/style.css` serve `output/style.css`, etc. Packaged `serve` follows the same resolution rules in its ReactPHP server loop, injects the live reload script into served HTML directly, and streams non-HTML files so large images, fonts, and media do not have to be buffered into memory before being sent.

If the output directory is missing or empty, a build runs automatically on the first request. The served directory is configured in `config/common/di/live-reload.php`.

## Live reload

When content files or templates change, the browser refreshes with the updated build.

How it works:

1. `LiveReloadMiddleware` injects a small JavaScript snippet before `</body>` in every HTML response.
2. The snippet opens an SSE (Server-Sent Events) connection to `/_live-reload`.
3. In normal Yii web serving, `LiveReloadAction` keeps that request open for up to 20 seconds while `FileWatcher` watches `content/` and `themes/`. In packaged serving, the ReactPHP server handles this endpoint directly before Yii dispatch and attaches all EventSource clients in the same worker to a shared inotify read stream.
4. `FileWatcher` waits for native `inotify` filesystem events instead of rescanning watched directories on a timer.
5. When a change is detected, `SiteBuildRunner` triggers a normal `yii build`, so live reload benefits from the same incremental build pipeline as manual builds.
6. After the build completes, the server sends a `reload` event and the browser refreshes.
