---
title: Configuration
weight: 2
---

# Configuration

You can configure the generator via `.smgen.yaml`, `.smgen-rc`, `.env`, environment variables, and pre/post hooks.

> **Note:** `smgen` reads configuration from your current working directory, where you invoke the script. By default it looks for `.smgen.yaml`, `.smgen-rc`, `.env`, `pages/`, `templates/`, and `static/` there.

## Configuration Sources

Configuration is loaded in this order:

1. `.smgen.yaml`
2. `.smgen-rc`
3. `.env`
4. exported environment variables and command-line env overrides

In practice, use:

- `.smgen.yaml` for fixed declarative values
- `.smgen-rc` when values need shell expansion or derived paths
- `.env` for local environment-specific overrides

## Environment Variables

### Product Details

- `BASE_URL` — base URL for generated site (NO TRAILING SLASH, default empty)
- `PRODUCT_NAME` — product/site name (default empty)
- `TITLE_PREFIX` — prefix for page titles
- `ORGANIZATION` — organization name (default empty)
- `TAGLINE` — product tagline (default empty)
- `HIGHLIGHT_STYLE` — Pandoc syntax highlighting theme (default `zenburn`)

### Page Styling

- `STYLES` — newline-separated list of CSS files to include (via `<link>`)
- `INLINE_STYLES` — newline-separated list of CSS files to inline in `<style>` tags

### Page Behavior

- `SCRIPTS` — newline-separated list of JS files to include (via `<script src>`)
- `INLINE_SCRIPTS` — newline-separated list of JS files to inline in `<script>` tags in `<head>`
- `BODY_SCRIPTS` — newline-separated list of JS files to include before `</body>`
- `INLINE_BODY_SCRIPTS` — newline-separated list of JS files to inline before `</body>`

### Project Directories

- `OUTPUT_DIR` — output directory (default `./docs`)
- `TEMPLATE_DIR` — template files directory (default `./templates`)
- `STATIC_DIR` — static assets directory (default `./static`)
- `PAGES_DIR` — source pages directory (default `./pages`)
- `DEV_PORT` — port used by `smgen serve` and `smgen watch` (default `8000`)
- `DEFAULT_THEME` — class applied to the root `<html>` element

### Executable Locations

- `PHP` — PHP executable (default `php`)
- `PANDOC` — Pandoc executable (default `pandoc`)
- `YQ` — `yq` executable (default `yq`)
- `UUID` — `uuid` executable (default `uuid`)
- `SMG_SEARCH` — `smgen-search` executable (default `smgen-search`)


## .smgen-rc Overrides

Create a `.smgen-rc` file at the root to override default variables (e.g., custom CSS or asset directory).

Example:

```bash
STYLES=$(cat <<-END
    /my-theme.css
END
)
```

## Hooks

- `before-smgen.sh` — script to run before the main build.
- `after-smgen.sh` — script to run after the build completes.

`before-smgen.sh` only runs for full `smgen build` invocations, not single-file builds triggered by `smgen build <path>` or `smgen watch`.
