---
title: Slimming PHAR size
summary: Keep runtime files, remove development files, and distinguish PHAR compression from binary compression.
draft: true
---

## Keep the download small

YiiPress includes the files and extensions it needs to run. Development tools stay out of the package.

The PHAR contains `config/`, `src/`, `themes/`, Composer runtime dependencies, and the `yii` script.
The archive filter removes files such as dependency tests, examples, documentation, and development-tool settings.
The build also strips comments, but keeps metadata that running code reads through reflection, PHP's way to inspect
classes at runtime.
Files inside the PHAR use gzip compression.

The PHP build includes selected extensions for tasks such as Markdown conversion, code highlighting, YAML parsing, and
XML output. It does not include every PHP extension. On Linux, the build uses musl to link libraries into the executable
and UPX to compress it. Other platforms use different packaging steps.
The PHAR, the executable, and the compressed download are different files, so their sizes differ too.

The application also avoids parts it does not need. It uses plain PHP templates without a view or widget stack.
Its preview server uses ReactPHP streams. It does not need FPM or Apache to run `yiipress serve`.

For the packaging steps, see [Binaries, PHAR, Docker](../binaries-phar-docker.md).
