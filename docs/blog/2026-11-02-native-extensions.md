---
title: Native extensions
summary: How PHP coordinates the build while C and Rust extensions process Markdown and code.
draft: true
---

## PHP connects the parts; native code processes the text

Yii3 supplies dependency injection, console integration, routing, and middleware for the preview application.
YiiPress uses these services to connect its content models, index, processors, templates, and file writers.

Two text-processing tasks use native extensions, which run compiled code:

- `ext-mdparser`, from `iliaal/mdparser`, converts Markdown to HTML with MD4C, a parser written in C.
- `ext-highlighter`, from `yiipress/highlighter`, highlights code with syntect, written in Rust.

The build includes these extensions in the PHP runtime. Users do not need to install separate Markdown or highlighting
libraries. PHP calls the extensions and controls the rest of the build.

For the content-processing pipeline, see [Engine](../engine.md).
