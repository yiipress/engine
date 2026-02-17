# Web application

The web application serves the built site for local development. It runs on FrankenPHP via `make up` or on PHP's built-in server via `yii serve`.

## Static file serving

The web application serves files from the `output/` directory. Requests for `/foo/` serve `output/foo/index.html`, requests for `/style.css` serve `output/style.css`, etc.

If the output directory is missing or empty, a build runs automatically on the first request. The served directory is configured in `config/common/di/live-reload.php`.

## Live reload

In dev mode (`APP_ENV=dev`), live reload is enabled automatically. When content files or templates change, the browser refreshes with the updated build.

How it works:

1. `LiveReloadMiddleware` injects a small JavaScript snippet before `</body>` in every HTML response.
2. The snippet opens an SSE (Server-Sent Events) connection to `/_live-reload`.
3. `LiveReloadAction` polls `content/` and `src/Render/Template/` for file changes every 500ms.
4. When a change is detected, `SiteBuildRunner` triggers a full `yii build` to regenerate the output directory.
5. After the build completes, the server sends a `reload` event and the browser refreshes.

Live reload is disabled in `test` and `prod` environments — the middleware passes responses through unchanged.
