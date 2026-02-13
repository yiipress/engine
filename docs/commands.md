# Console commands

All commands are run via the `yii` CLI entry point (or `composer serve` for the dev server).

## `yii build`

Generates static HTML content from source files.

```
yii build [--content-dir=content] [--output-dir=output]
```

**Options:**

- `--content-dir`, `-c` — path to the content directory (default: `content`). Absolute or relative to project root.
- `--output-dir`, `-o` — path to the output directory (default: `output`). Absolute or relative to project root.

The command:

1. Parses site config, navigation, collections, authors, and entries from the content directory.
2. Cleans the output directory.
3. Writes each entry as `index.html` at its resolved permalink path.

## `yii serve`

Starts PHP built-in web server for local development.

```
yii serve [--port=8080]
```

Alternatively, use `composer serve` which disables the process timeout.

## `yii` / `yii list`

Shows available commands and help.
