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
- **uuid** (UUID generator)

Optional:

- **inotify-tools** for `smgen watch`
- **aspell** for `smgen proofread`
- **smgen-search** for generating `search.bin`

Example install on Debian/Ubuntu:

```bash
apt install php php-yaml bash pandoc uuid inotify-tools aspell

YQ_VERSION=v4.47.2
wget -qO /usr/local/bin/yq https://github.com/mikefarah/yq/releases/download/${YQ_VERSION}/yq_linux_amd64
chmod +x /usr/local/bin/yq
```

## Install

To install `smgen` system-wide and set up an update path, run:

```bash
curl -fsSL https://seanmorris.github.io/smgen/install.sh | sudo bash
```

This clones the repository into `/usr/share/smgen`, creates a `smgen` symlink in `/usr/local/bin`, and can be used again later to update the installation.

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
smgen build      Build Markdown pages into HTML
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

In practice:

- use `.smgen.yaml` for fixed, declarative values
- use `.smgen-rc` when values need shell expansion, derived URLs, conditionals, or other runtime logic
- use `.env` for local environment-specific overrides
- if the same variable is defined in multiple sources, later-loaded shell config wins over earlier YAML config

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

Assets can be configured as newline-separated lists:

- `STYLES`
- `INLINE_STYLES`
- `SCRIPTS` for `<head>` script tags
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
SCRIPTS:
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

SCRIPTS=$( cat <<-END
	${BASE_URL}/main.js
END
)
```

For the full documentation source used by this repo, see:

- `pages/configuration.md`
- `pages/customization.md`
- `pages/writing-pages.md`
- `pages/cli.md`

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

If `smgen-search` is installed and available on `PATH`, `smgen build` will generate `search.bin` from your page sources. If it is not installed, the rest of the build still completes.

If you install it locally instead of globally, point `SMG_SEARCH` at the executable, for example `./node_modules/.bin/smgen-search`.

## Testing

Run the CLI end-to-end test suite with:

```bash
bash ./tests/run.sh
```

The suite covers the core build flow, custom output directories, sitemap generation, and static asset output paths.

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
