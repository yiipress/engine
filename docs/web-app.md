# Web application

The web application serves the built site for local development. It runs on FrankenPHP via `make up` or on PHP's built-in server via `yii serve`. Live reload requires PHP `ext-inotify`; the Docker images install it by default.

## Static file serving

The web application serves files from the `output/` directory. Requests for `/foo/` serve `output/foo/index.html`, requests for `/style.css` serve `output/style.css`, etc.

If the output directory is missing or empty, a build runs automatically on the first request. The served directory is configured in `config/common/di/live-reload.php`.

## Live reload

When content files or templates change, the browser refreshes with the updated build.

How it works:

1. `LiveReloadMiddleware` injects a small JavaScript snippet before `</body>` in every HTML response.
2. The snippet opens an SSE (Server-Sent Events) connection to `/_live-reload`.
3. `LiveReloadAction` keeps that request open for up to 20 seconds while `FileWatcher` watches `content/` and `themes/`.
4. `FileWatcher` waits for native `inotify` filesystem events instead of rescanning watched directories on a timer.
5. When a change is detected, `SiteBuildRunner` triggers a normal `yii build`, so live reload benefits from the same incremental build pipeline as manual builds.
6. After the build completes, the server sends a `reload` event and the browser refreshes.
