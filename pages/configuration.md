---
title: Configuration
weight: 2
---

# Configuration

You can configure the generator via environment variables, `.smgen-rc`, and pre/post hooks.

> **Note:** `smgen` looks for `pages/`, `templates/`, `static/`, and `.smgen-rc` in your current working directory (where you invoke the script). All paths and hooks can be customized via environment variables or a `.smgen-rc` file placed alongside the script.

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

### Executable Locations

- `PHP` — PHP executable (default `php`)
- `PANDOC` — Pandoc executable (default `pandoc`)
- `YQ` — `yq` executable (default `yq`)
- `UUID` — `uuid` executable (default `uuid`)


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
