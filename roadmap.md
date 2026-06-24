# YiiPress Roadmap

## Priority 0: Core

- [x] Reevaluate content structure to support all features planned in the roadmap
- [x] Draft architecture in `docs/architecture.md`
- [x] Implement superfast parsing of each content file type
- [x] Consider that as less as possible should be loaded into memory at any time
- [x] Consider using fibers for parallel processing
- [x] Caching layer for parsed markdown/front matter between builds
- [x] Benchmarking suite (e.g., generate 10k entries) to track performance regressions
- [x] Expand benchmark by adding a separate test for entries to include links to another entries, 10 pages of text, images, differently styled text, tables.

## Priority 1: Essential blog features

- [x] Collection and site-wide RSS/Atom/JSON Feed generation. Content should be generated using same plugins as for HTML content
- [x] Sitemap generation
- [x] Tags and categories (taxonomies)
- [x] Draft support (front matter `draft: true` flag, exclude from build by default)
- [x] Entry date handling and chronological sorting
- [x] Entry summary/excerpt (auto-generated or manual via front matter)
- [x] Syntax highlighting for code blocks that is a processor and is server-rendered
- [x] Permalink customization (per-entry via front matter, site-wide patterns)
- [x] Pagination configuration (entries per page, URL patterns)
- [x] Entry sorting options (custom sort fields like weight/order for non-blog collections)
- [x] Explicit collection entry order (`order` field in `_collection.yaml`)
- [x] Static/standalone pages (pages outside of collections, e.g., "about", "contact")
- [x] Navigation / menu system
- [x] Content scheduling (future-dated entries excluded from build by default)
- [x] Author support (author metadata in front matter, author pages/archives)
- [x] Date-based archive pages (yearly, monthly)
- [x] Cross-references / internal linking helpers (shorthand syntax to link between entries, prevents broken links on permalink changes)
- [x] Markdown extensions configuration (enable/disable footnotes, definition lists, strikethrough, tables, etc.)
- [x] KaTeX rendering assets for LaTeX math output
- [x] Taxonomy term pagination

## Priority 2: Documentation

- [x] `docs/configuration.md` — document all configuration options (site name, charset, locale, base URL, pagination, etc.)
- [x] `docs/front-matter.md` — document supported front matter fields (title, date, slug, draft, tags, categories, custom fields) — covered in `docs/content.md`
- [x] `docs/structure.md` — document expected project directory layout (`content/`, `templates/`, `public/`, `assets/`, `plugins/`, `config/`) — covered in `docs/content.md`
- [x] `docs/quickstart.md` — step-by-step guide: create first post, run dev server, build static site
- [x] Expand `docs/commands.md` — add flags, options, and usage examples for each command
- [x] Expand `docs/plugins.md` — document plugin API, lifecycle hooks, registration, configuration
- [x] Expand `docs/templates.md` — document available template variables, partials/includes, example templates
- [x] Make user-facing docs binary-first and split engine developer details into `docs/engine.md`

## Priority 3: Developer experience

- [x] Live reload / file watching during `yiipress serve`
- [x] PHAR and static PHP binary packaging
- [x] Worker-mode serving for PHAR and static PHP binary packages
- [x] Linux, macOS, Windows, and distroless Docker release packaging
- [x] Tag release workflow for binaries, PHAR, and binary-only distroless image
- [x] Reusable GitHub Action for downloading the Linux binary and building third-party sites
- [x] `yiipress new` command — scaffold new entries from archetypes/templates
- [x] `yiipress init` command — scaffold initial content structure
- [x] Incremental builds (only rebuild changed files)
- [x] Smaller static package by removing unused runtime extension dependencies
- [x] Build diagnostics (warn on broken internal links, missing images, invalid front matter)
- [x] `yiipress clean` command — clear build output and caches
- [x] `yiipress check:links` command — validate generated links and anchors
- [x] Dry run mode for build — show what would be generated without writing files
- [x] No-write build mode for render-vs-filesystem performance diagnostics
- [x] `serve` overlay button to open the current markdown source in a configured editor

## Priority 4: Templates and theming

- [x] Theme system — installable/distributable themes
- [x] Auto-register project themes from `themes/<name>/` for binary users
- [x] Namespaced theme assets for installable themes
- [x] Template partials/includes support
- [x] Template helper functions documentation
- [x] Site data files exposed to templates (`content/data/*.yaml` or `.yml` as `$data`)
- [x] Multiple layout support (per-entry layout selection via front matter)
- [x] Beautiful default theme
- [x] VuePress-style documentation layout with left sidebar navigation and right document table of contents

## Priority 5: Asset pipeline

- [x] Asset fingerprinting / cache busting
- [x] Static file copying (fonts, downloads, PDFs from source to output)
- [x] Root-relative asset URLs stay valid when deploying under a subdirectory
- [x] Nightly Linux binary published for GitHub Actions preview builds
- [x] Configurable generated HTML and CSS/JavaScript asset minification

## Priority 6: SEO and web standards

- [x] Open Graph / meta tag helpers
- [x] Canonical URL support
- [x] Configurable `robots.txt` generation
- [x] Redirect support (e.g., when changing permalinks, output redirect HTML or config)
- [x] Entry aliases in front matter for old URL redirects
- [x] Root-relative redirects resolve against deployment paths from `base_url`
- [x] 404 page in static build output for static hosting providers (Netlify, GitHub Pages, etc.)
- [x] Deployment helpers/docs for common static hosts (GitHub Pages, Netlify, Vercel, Cloudflare Pages)

## Priority 7: Content extensions

- [x] Built-in shortcodes (YouTube, Vimeo, figure, etc.) as a plugin
- [x] Table of contents generation from headings as a plugin
- [x] Diagram support (Mermaid) as a plugin
- [x] oEmbed support (auto-expanding URLs to embeds) as a plugin

## Priority 8: Advanced features

- [x] Related content suggestions as a plugin
- [x] Multilingual / i18n support
- [x] UI language selector independent from content language, remembered client-side
- [x] Search as a plugin
- [x] Hooks / events system (before build, after build, before render, after render) for plugin architecture


## Priority 9: Data importers

- [ ] WordPress
- [ ] Jekyll
- [ ] Hugo
- [ ] Medium exported Markdown
- [ ] Ghost
- [x] Telegram export
