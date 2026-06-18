# Preview

The preview server serves the built site during local development:

```bash
./yiipress serve
```

The static binary is the recommended way to run preview. It resolves `content/` and `output/` from the current directory by default, and custom paths can be passed with `--content-dir` and `--output-dir`.

The command validates paths before opening the socket. The content directory must exist, and the output directory must exist or be creatable and writable. If either check fails, run it with explicit paths such as `./yiipress serve --content-dir=content --output-dir=output`.

## Static file serving

The preview server serves files from the configured output directory. Requests for `/foo/` serve `<output-dir>/foo/index.html`, requests for `/style.css` serve `<output-dir>/style.css`, and so on.

The source-open overlay posts the current browser path to the framework-routed `/_open-source` action. The action resolves that path to an output file, looks up the corresponding markdown source in the build manifest, verifies that the source is inside the content directory, and launches the configured `editor` command from `content/config.yaml`. If no editor is configured, the server uses the platform opener (`open` on macOS, `xdg-open` on Linux, or `start` through `cmd` on Windows).

If the output directory is empty after startup validation, a build runs automatically on the first request. Implementation details are covered in [Engine](engine.md#serve-mode).

## Live reload

When content files or templates change, the browser refreshes with the updated build.

How it works:

1. `DevHtmlInjector` injects a small JavaScript snippet before `</body>` in every served HTML response.
2. The snippet opens an SSE (Server-Sent Events) connection to `/_live-reload`.
3. The ReactPHP server handles this endpoint directly and attaches all EventSource clients in the same worker to a shared inotify read stream.
4. When a change is detected, `SiteBuildRunner` triggers a normal build, so live reload benefits from the same incremental build pipeline as manual builds. Rebuilds are serialized with a lock so multiple preview workers do not write the same output concurrently.
5. After a successful build, the server sends a `reload` event and the browser refreshes. If the build fails, the page is not reloaded and the build output is shown in a fixed on-page error panel.
