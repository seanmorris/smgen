---
title: Configuration
weight: 3
llms:
  section: reference
  description: Configure paths, assets, search, menus, feeds, and build behavior.
---

# Configuration

SMGen reads project configuration from the current working directory. Run every
command from the project root unless you supply all required paths through the
calling environment.

## Precedence

<!-- <what keywords="configuration order, which value wins, project yaml override"> -->
<!-- <where keywords="local override, override project yaml, configuration source"> -->
SMGen resolves supported settings in this order, from lowest to highest:

1. built-in defaults
2. `.smgen.yaml`
3. `.smgen.<environment>.yaml`
4. `.smgen-rc`
5. `.env`
6. values exported by the calling process

Set `SMGEN_ENVIRONMENT=production` to load `.smgen.production.yaml`. The legacy
lowercase `environment` setting selects the same file when
`SMGEN_ENVIRONMENT` is empty. Environment names may contain letters, numbers,
periods, underscores, and hyphens.

Use `.smgen.yaml` for fixed declarative values. Use `.smgen-rc` for shell
expansion or computed values. Use `.env` for local overrides. SMGen sources the
two shell files, so only use configuration from a trusted project.
<!-- </where> -->
<!-- </what> -->

## Site and Page Settings

The following scalar settings work in YAML, shell configuration, or the calling
environment:

- `BASE_URL`: prefix for generated links and sitemap URLs. Default: empty.
- `PRODUCT_NAME`: site name shown by the default header. Default: empty.
- `TAGLINE`: subtitle shown by the default header. Default: empty.
- `ORGANIZATION`: organization shown by the default footer. Default: empty.
- `TITLE_PREFIX`: prefix for HTML page titles. Default: empty.
- `DEFAULT_THEME`: class on the root `html` element. Default: `theme-default`.
- `HIGHLIGHT_STYLE`: Pandoc syntax highlight style. Default: `zenburn`.
- `HEADER`: default header include. Default: `templates/header.php`.
- `FOOTER`: default footer include. Default: `templates/footer.php`.
- `DEV_PORT`: port used by `serve` and `watch`. Default: `8000`.
- `SMGEN_ENGINE`: build implementation, `php` or `bash`. Default: `php`.
- `BUILD_JOBS`: number of Pandoc processes that convert pages at the same time,
  or `auto` for one per CPU. Set `1` to convert pages one at a time. Default: `auto`.

SMGen removes a trailing slash from `BASE_URL` when it joins URLs, so either
form is accepted.

## Project Paths

- `OUTPUT_DIR`: generated site directory. Default: `./docs`.
- `PAGES_DIR`: Markdown and source page directory. Default: `./pages`.
- `TEMPLATE_DIR`: page and include template directory. Default: `./templates`.
- `STATIC_DIR`: project static asset directory. Default: `./static`.
- `HELPERS_DIR`: project helper override directory. Default: `./helpers`.

All relative paths resolve from the current working directory. Project copies
of `domain.lua`, `navigation-data.php`, `llms.php`, `rss.php`, `sitemap.php`, and
`navbar.php` can override their documented core behavior through
`HELPERS_DIR`. Template selection and asset copying reject paths that escape
their configured roots.

## Styles and Scripts

These list settings accept YAML sequences or newline-separated shell values:

- `STYLES`: stylesheet URLs rendered as `link` elements.
- `INLINE_STYLES`: local CSS files passed to Pandoc as inline header content.
- `SCRIPTS`: blocking script URLs in the document head.
- `DEFER_SCRIPTS`: deferred script URLs in the document head.
- `INLINE_SCRIPTS`: local JavaScript files inlined in the document head.
- `BODY_SCRIPTS`: script URLs inserted before the closing body tag.
- `INLINE_BODY_SCRIPTS`: local JavaScript files inlined before the closing body tag.

YAML example:

```yaml
STYLES:
  - /default.css
  - /theme.css
DEFER_SCRIPTS:
  - /main.js
BODY_SCRIPTS:
  - /analytics.js
```

Shell example:

```bash
STYLES=$(cat <<-END
/default.css
/theme.css
END
)
```

SMGen resolves root-relative asset URLs against `BASE_URL`. Absolute HTTP URLs,
protocol-relative URLs, data URLs, `mailto` links, and `tel` links pass through
unchanged.

## Core Assets

Project assets from `STATIC_DIR` always overlay the staged output.

Set `INHERIT_CORE_STATIC=1` to copy every built-in SMGen asset first. Leave it
at its default value of `0` when the project owns every published asset.

Use `CORE_ASSETS` to copy selected built-in files instead:

```yaml
CORE_ASSETS:
  - scrollbar.css
  - default.css
  - main.js
  - search.svg
```

`CORE_ASSETS` takes precedence over `INHERIT_CORE_STATIC`. Each entry must stay
inside SMGen's built-in `static/` directory.

## Search

- `SEARCH_ENABLED`: `auto`, `1`, or `0`. Default: `auto`.
- `SEARCH_READER`: browser reader module URL. Default: `/vendor/smgen-search/SearchReader.mjs`.
- `SMG_SEARCH`: search index command. Default: `smgen-search`.

With `SEARCH_ENABLED=auto`, search is enabled when `SMG_SEARCH` is available or
`STATIC_DIR/search.bin` exists. With `SEARCH_ENABLED=1`, a build fails unless it
can run the indexer or copy a prebuilt index. With `SEARCH_ENABLED=0`, the
default header omits the search interface.

When the indexer is available, each build creates a new `search.bin` from
`PAGES_DIR`. The default browser script waits for focus or a query before it
loads the reader and index.

## Navigation

One legacy setting remains supported:

- `NAVIGATION_WIDGETS`: the `tree` widget for sites without `MENU_REGIONS`.
  Default: `tree`.

`PLUGINS` is an ordered list of plugin manifest paths, local npm package names,
or structured activations. Structured entries can set `use`, `options`,
`pages`, and `watch`:

```yaml
PLUGINS:
  - use: smgen-assistant
    options:
      config: .smgen-assistant.yaml
    pages:
      include: ['**/*']
```

Use YAML for structured entries. Shell configuration and environment variables
accept newline-separated manifest paths and package names.

`MENUS` and `MENU_REGIONS` are structured mappings and belong in
`.smgen.yaml` or an environment-specific YAML file. See
[Navigation and Plugins](navigation.html) for their schema and rendering API.
See [Build Plugins](plugins.html) for versioned manifests, page selectors,
assets, hooks, generated pages, and watch paths.

## RSS Feeds

`RSS_FEEDS` is a mapping of named RSS 2.0 feeds. Define it in `.smgen.yaml` or
an environment-specific YAML file. Each definition requires `title` and
`description`. It may also set `path`, `link`, `language`, `limit`,
`namespaces`, and `channel`.

```yaml
RSS_FEEDS:
  main:
    path: /rss.xml
    title: Example Site
    description: Recent articles
    link: /
    language: en-us
    limit: 20
```

See [RSS Feeds](rss.html) for feed definitions, page membership, dates, and
custom channel and item data.

## llms.txt

`LLMS` is a mapping that enables a curated Markdown index of selected HTML
pages. Define the index title, summary, optional details, and named sections in
`.smgen.yaml` or an environment-specific YAML file:

```yaml
LLMS:
  title: Example Documentation
  summary: Build and publish an Example site.
  sections:
    start:
      title: Start Here
      weight: 0
    reference:
      title: Reference
      weight: 20
```

The build writes `/llms.txt` only when `LLMS` is enabled. See
[llms.txt](llms.html) for page and directory selection, fallback fields, output
format, and template discovery.

## Executables and Proofreading

- `PHP`: PHP command. Default: `php`.
- `PANDOC`: Pandoc command. Default: `pandoc`.
- `YQ`: `yq` command used by the Bash fallback. Default: `yq`.
- `SMG_SEARCH`: search index command. Default: `smgen-search`.
- `ASPELL`: spell-check command. Default: `aspell`.
- `ASPELL_DICT`: personal dictionary path. Default: `aspell.txt`.

## Hooks

Place executable shell logic in either project hook:

- `before-smgen.sh`: runs before navigation compilation and page rendering.
- `after-smgen.sh`: runs after the staged output replaces the previous output.

SMGen runs hooks with Bash. A failed before hook stops the build before output
replacement. A failed after hook restores the previous output. Versioned
plugin hooks run against staged output and are described in
[Build Plugins](plugins.html).

## Output Ownership

A successful build replaces `OUTPUT_DIR` completely. Files left in an older
output disappear. Keep authored pages, templates, helpers, and assets in their
source directories.

SMGen refuses an output path that is a symbolic link, contains the project
root, or contains a configured source directory. It creates the replacement in
a temporary sibling directory so the final rename stays on the same
filesystem.
