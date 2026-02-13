# YiiPress Roadmap

## Priority 0: Core

- [x] Reevaluate content structure to support all features planned in the roadmap
- [x] Draft architecture in `docs/architecture.md`
- [x] Implement super-fast parsing of each content file type
- [x] Consider that as less as possible should be loaded into memory at any time
- [x] Consider using fibers for parallel processing
- [x] Caching layer for parsed markdown/front matter between builds
- [x] Benchmarking suite (e.g., generate 10k entries) to track performance regressions
- [x] Expand benchmark by adding a separate test for entries to include links to another entries, 10 pages of text, images, differently styled text, tables.

## Priority 1: Essential blog features

- [x] RSS/Atom feed generation. Content should be generated using same plugins as for HTML content
- [x] Sitemap generation
- [x] Tags and categories (taxonomies)
- [x] Draft support (front matter `draft: true` flag, exclude from build by default)
- [x] Entry date handling and chronological sorting
- [x] Entry summary/excerpt (auto-generated or manual via front matter)
- [ ] Syntax highlighting for code blocks that is a plugin and is server-rendered
- [x] Permalink customization (per-entry via front matter, site-wide patterns)
- [x] Pagination configuration (entries per page, URL patterns)
- [x] Entry sorting options (custom sort fields like weight/order for non-blog collections)
- [x] Static/standalone pages (pages outside of collections, e.g., "about", "contact")
- [ ] Navigation / menu system
- [x] Content scheduling (future-dated entries excluded from build by default)
- [ ] Author support (author metadata in front matter, author pages/archives)
- [ ] Date-based archive pages (yearly, monthly)
- [ ] Cross-references / internal linking helpers (shorthand syntax to link between entries, prevents broken links on permalink changes)
- [ ] Markdown extensions configuration (enable/disable footnotes, definition lists, strikethrough, tables, etc.)

## Priority 2: Documentation

- [x] `docs/configuration.md` — document all configuration options (site name, charset, locale, base URL, pagination, etc.) — covered in `docs/config.md`
- [x] `docs/front-matter.md` — document supported front matter fields (title, date, slug, draft, tags, categories, custom fields) — covered in `docs/content.md`
- [x] `docs/structure.md` — document expected project directory layout (`content/`, `templates/`, `public/`, `assets/`, `plugins/`, `config/`) — covered in `docs/content.md`
- [ ] `docs/quickstart.md` — step-by-step guide: create first post, run dev server, build static site
- [x] Expand `docs/commands.md` — add flags, options, and usage examples for each command
- [ ] Expand `docs/plugins.md` — document plugin API, lifecycle hooks, registration, configuration
- [ ] Expand `docs/template.md` — document available template variables, partials/includes, example templates

## Priority 3: Developer experience

- [ ] Live reload / file watching during `yiipress serve`
- [ ] `yiipress new` command — scaffold new entries from archetypes/templates
- [ ] Incremental builds (only rebuild changed files)
- [ ] Environment-specific configuration documentation (document existing `config/environments/`)
- [ ] Build diagnostics (warn on broken internal links, missing images, invalid front matter)
- [ ] `yiipress clean` command — clear build output and caches
- [ ] Dry run mode for build — show what would be generated without writing files

## Priority 4: Templates and theming

- [ ] Theme system — installable/distributable themes
- [ ] Template partials/includes support
- [ ] Template helper functions documentation
- [ ] Multiple layout support (per-entry layout selection via front matter)

## Priority 5: Asset pipeline

- [ ] Image optimization (resize, compress, modern formats)
- [ ] CSS/JS processing (minification)
- [ ] Asset fingerprinting / cache busting
- [ ] Static file copying (fonts, downloads, PDFs from source to output)

## Priority 6: SEO and web standards

- [ ] Open Graph / meta tag helpers
- [ ] Canonical URL support
- [ ] Configurable `robots.txt` generation
- [ ] Redirect support (e.g., when changing permalinks, output redirect HTML or config)
- [ ] 404 page in static build output for static hosting providers (Netlify, GitHub Pages, etc.)
- [ ] Deployment helpers/docs for common static hosts (GitHub Pages, Netlify, Vercel, Cloudflare Pages)

## Priority 7: Content extensions

- [ ] Built-in shortcodes (YouTube, Vimeo, figure, etc.) as a plugin
- [ ] Table of contents generation from headings as a plugin
- [ ] Diagram support (Mermaid) as a plugin
- [ ] oEmbed support (auto-expanding URLs to embeds) as a plugin

## Priority 8: Advanced features

- [ ] Related content suggestions as a plugin
- [ ] Multilingual / i18n support
- [ ] Search as a plugin
- [ ] Hooks / events system (before build, after build, before render, after render) for plugin architecture


## Priority 9: Data importers

- [ ] WordPress
- [ ] Jekyll
- [ ] Hugo
- [ ] Medium exported Markdown
- [ ] Ghost
- [ ] Telegram export

