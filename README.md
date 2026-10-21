# SMGen
*Static Site Generator*

Bash-driven static-site generator. Converts Markdown files into a fully templated HTML website using PHP, `yq`, and Pandoc.

<a target = "_blank" href = "https://seanmorris.github.io/smgen/">
<img width = "100%" src = "https://seanmorris.github.io/smgen/banner-cropped.jpg" />
</a>

## What It Does

SMGen builds a static site from a small set of moving parts:

- Markdown pages in `pages/`
- PHP templates in `templates/`
- Static assets in `static/`
- Generated HTML in `docs/` by default

Out of the box it supports:

- YAML frontmatter
- shared header/footer/page templates
- syntax-highlighted code blocks via Pandoc
- automatic sitemap generation
- optional search index generation via `smgen-search`
- local preview with `smgen serve`
- filesystem watching with `smgen watch`

## Prerequisites

Required for `smgen build`:

- **Bash** (shell, version >= 4.0)
- **PHP** command-line
- **PHP YAML extension** (`php-yaml`)
- **yq** (YAML processor)
- **Pandoc** (Markdown to HTML converter)

Optional:

- **inotify-tools** for `smgen watch`
- **aspell** for `smgen proofread`
- **smgen-search** for generating `search.bin`

Example install on Debian/Ubuntu:

```bash
apt install php php-yaml bash pandoc inotify-tools aspell

YQ_VERSION=v4.47.2
wget -qO /usr/local/bin/yq https://github.com/mikefarah/yq/releases/download/${YQ_VERSION}/yq_linux_amd64
chmod +x /usr/local/bin/yq
```

## Install

To install `smgen` system-wide and set up an update path, run:

```bash
curl -fsSL https://seanmorris.github.io/smgen/install.sh | sudo bash
```

This clones the repository into `/usr/share/smgen`, creates a `smgen` symlink in `/usr/local/bin`, and can be used again later to update the installation. Updates download into a staging directory and replace the installed checkout only after the clone succeeds. The installer refuses to overwrite an existing non-Git directory at that path.

## Quickstart

Initialize a new site:

```bash
mkdir my-site
cd my-site
smgen init
```

Build the site:

```bash
smgen build
```

Serve the generated output locally:

```bash
smgen serve
```

Open <http://localhost:8000> in your browser.

To rebuild automatically while you work:

```bash
smgen watch
```

## Project Layout

Typical site structure:

```text
pages/      # Markdown source files
templates/  # PHP templates
static/     # Images, CSS, JS, fonts, other assets
docs/       # Generated output (default)
.smgen-rc   # Shell-based local configuration
.smgen.yaml # YAML-based configuration
```

## Commands

```text
smgen init       Initialize a new site in the current directory
smgen build      Atomically rebuild the complete site
smgen watch      Build, serve, and rebuild on filesystem changes
smgen serve      Serve the generated output locally
smgen proofread  Spell-check one page or all pages with aspell
smgen help       Show usage
```

## Configuration

SMGen reads configuration from the current working directory. The main inputs are:

- `.smgen.yaml`
- `.smgen-rc`
- `.env`
- page frontmatter

Configuration precedence, from lowest to highest, is:

1. built-in defaults
2. `.smgen.yaml`
3. `.smgen.<environment>.yaml`, selected with `SMGEN_ENVIRONMENT`
4. `.smgen-rc`
5. `.env`
6. variables supplied by the calling environment

In practice:

- use `.smgen.yaml` for fixed, declarative values
- use `.smgen-rc` when values need shell expansion, derived URLs, conditionals, or other runtime logic
- use `.env` for local environment-specific overrides
- use `.smgen.<environment>.yaml` for named deployment settings

Common settings:

- `BASE_URL` — base URL for generated links and assets
- `OUTPUT_DIR` — output directory (default `./docs`)
- `PAGES_DIR` — source pages directory (default `./pages`)
- `TEMPLATE_DIR` — template directory (default `./templates`)
- `STATIC_DIR` — static asset directory (default `./static`)
- `DEFAULT_THEME` — class applied to `<html>`
- `TITLE_PREFIX` — page title prefix
- `PRODUCT_NAME` — product/site name
- `TAGLINE` — subtitle in the default header
- `ORGANIZATION` — organization name used in the footer
- `HIGHLIGHT_STYLE` — Pandoc syntax highlighting theme
- `SMG_SEARCH` — search index generator command
- `SEARCH_ENABLED` — `auto`, `1`, or `0` (default `auto`)
- `SEARCH_READER` — local browser reader module path
- `NAVIGATION_WIDGETS` — ordered list of sidebar widgets; supports `tree` and `archive`
- `ARCHIVE_TITLE` — heading for the archive widget (default `Archive`)
- `PLUGINS` — ordered list of menu plugin manifest paths
- `MENUS` — named static menu definitions
- `MENU_REGIONS` — menus placed in template regions

Assets can be configured as newline-separated lists:

- `STYLES`
- `INLINE_STYLES`
- `SCRIPTS` for `<head>` script tags
- `DEFER_SCRIPTS` for non-blocking `<head>` script tags
- `INLINE_SCRIPTS` for inline `<head>` scripts
- `BODY_SCRIPTS`
- `INLINE_BODY_SCRIPTS`

Use YAML when the values are fixed:

```yaml
BASE_URL: http://localhost:8000
OUTPUT_DIR: ./docs
PAGES_DIR: ./pages
TEMPLATE_DIR: ./templates
STATIC_DIR: ./static
DEFAULT_THEME: theme-default
PRODUCT_NAME: My Site
TAGLINE: Static site docs
ORGANIZATION: Example Org
TITLE_PREFIX: My Site
HIGHLIGHT_STYLE: zenburn
STYLES:
  - http://localhost:8000/default.css
  - http://localhost:8000/cosmic.css
DEFER_SCRIPTS:
  - http://localhost:8000/main.js
```

Use `.smgen-rc` when the values are derived:

```bash
#!/usr/bin/env bash

DEV_PORT=8000
BASE_URL=${BASE_URL:-"http://localhost:${DEV_PORT}"}
OUTPUT_DIR=./docs
DEFAULT_THEME=theme-default
PRODUCT_NAME="My Site"
TAGLINE="Static site docs"
ORGANIZATION="Example Org"
TITLE_PREFIX="My Site"

STYLES=$( cat <<-END
	${BASE_URL}/default.css
END
)

DEFER_SCRIPTS=$( cat <<-END
	${BASE_URL}/main.js
END
)
```

For the full documentation source used by this repo, see:

- `pages/configuration.md`
- `pages/customization.md`
- `pages/writing-pages.md`
- `pages/cli.md`

## Named Menus

SMGen compiles named menus and regions before rendering any page. A menu uses the `flat`, `tree`, or `timeline` renderer:

```yaml
MENUS:
  primary:
    label: Primary
    renderer: flat
    links:
      - id: project
        href: https://example.com/project
        title: Project
  documentation:
    label: Documentation
    renderer: tree
    hierarchy: source
  archive:
    label: Timeline
    renderer: timeline
MENU_REGIONS:
  header: [primary]
  sidebar: [documentation, archive]
```

Pages can join more than one menu and customize each link independently:

```yaml
---
title: Release notes
date: 2026-10-21
modified: 2026-10-22T14:30:00Z
menus:
  primary:
    title: Releases
    weight: 20
  archive: true
---
```

Use `renderMenuRegion('header')` or `renderMenu('primary')` in PHP templates. Link settings support `title`, `parent`, `weight`, `expanded`, `variant`, `icon`, and `description`. Set a membership to `false` to veto a plugin selector.

Timeline menus produce a Blogger-style linked calendar and expandable year/month groups. A selected page uses `date`, then `created`. If both are absent, SMGen derives a creation time from the filesystem or Git and writes it once as `created` in that page's source frontmatter. Author-supplied `modified` controls the sitemap's `lastmod`.

Menu HTML and hierarchy are static. Timeline expansion uses native `<details>`, adds no JavaScript or runtime request, and links `archive.css` only when a populated timeline is placed in a region.

Legacy `leftBarLink`, `leftBarShow`, `NAVIGATION_WIDGETS`, `archive`, and `ARCHIVE_TITLE` remain supported. New `menus` metadata replaces legacy membership inference for that page.

### Menu plugins

List plugin manifests explicitly with `PLUGINS`. A manifest can contribute menus, links, and selectors:

```yaml
id: guides
menus:
  documentation:
    selectors:
      - include: ["guides/**/*.md"]
        exclude: ["guides/drafts/**"]
        where:
          audience: public
        defaults:
          parent: guides.root
    links:
      - id: guides.root
        group: true
        title: Guides
```

Selectors use source-relative paths and exact frontmatter matches. A manifest may name an `alter` PHP file that returns a callable; configured plugin hooks run in order, followed by an optional `helpers/menu-alter.php` site hook.

## Syntax Highlighting

Set the syntax highlighting theme in `.smgen-rc`:

```bash
HIGHLIGHT_STYLE=zenburn
```

Available options:

- `pygments`
- `tango`
- `espresso`
- `kate`
- `monochrome`
- `breezedark`
- `haddock`
- `zenburn`

## Search Index

With `SEARCH_ENABLED=auto`, SMGen enables search when `smgen-search` is on `PATH` or `static/search.bin` already exists. Set `SEARCH_ENABLED=1` to require a usable index, or `0` to omit the search UI. When the CLI is available, every build regenerates `search.bin` from the page sources.

If you install it locally instead of globally, point `SMG_SEARCH` at the executable, for example `./node_modules/.bin/smgen-search`.

The browser reader is served from `static/vendor/smgen-search`; it and the index load only when a visitor focuses or uses search.

## Testing

Run the CLI end-to-end test suite with:

```bash
bash ./tests/run.sh
```

The suite covers configuration precedence, frontmatter, complete output replacement, failed-build rollback, hooks, custom directories, path safety, sitemap generation, navigation escaping, static assets, and search loading.

## Contributing

For local validation:

```bash
bash ./tests/run.sh
./smgen.sh build
```

If you are changing templates, asset handling, or config behavior, verify both the generated output and the test suite.

## Limitations

- `smgen watch` currently relies on Linux `inotify-tools`
- `smgen proofread` requires `aspell`
- search index generation requires `smgen-search`

## Build Guarantees

Each build renders the entire site in a temporary sibling directory and replaces `OUTPUT_DIR` only after generation succeeds. This removes stale files and keeps the last successful output when rendering or an after-build hook fails. `smgen build <path>` validates the path but still performs a complete rebuild so navigation, sitemap, and cross-page metadata remain correct.

Treat `OUTPUT_DIR` as generated content: a successful build replaces it completely. Keep authored files in `pages/`, `static/`, or another source directory.

## Documentation Source

This repository is both the generator and its documentation site.

Documentation is authored in Markdown under `pages/`:

- `pages/index.md` — Introduction & Getting Started
- `pages/configuration.md` — Configuration
- `pages/customization.md` — Customization
- `pages/writing-pages.md` — Writing Pages
- `pages/cli.md` — Command Reference
- `pages/examples/basic-usage.md` — Basic Usage Example
- `pages/deployment.md` — Deployment
- `pages/faq.md` — FAQ & Troubleshooting
- `pages/LICENSE.md` — License

Run `./smgen.sh build` to regenerate the HTML in `docs/`.
