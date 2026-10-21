---
title: Configuration
weight: 2
---

# Configuration

You can configure the generator via `.smgen.yaml`, `.smgen-rc`, `.env`, environment variables, and pre/post hooks.

> **Note:** `smgen` reads configuration from your current working directory, where you invoke the script. By default it looks for `.smgen.yaml`, `.smgen-rc`, `.env`, `pages/`, `templates/`, and `static/` there.

## Configuration Sources

Configuration is loaded in this order:

1. built-in defaults
2. `.smgen.yaml`
3. `.smgen.<environment>.yaml`, selected with `SMGEN_ENVIRONMENT`
4. `.smgen-rc`
5. `.env`
6. exported environment variables and command-line overrides

In practice, use:

- `.smgen.yaml` for fixed declarative values
- `.smgen-rc` when values need shell expansion or derived paths
- `.env` for local environment-specific overrides
- `.smgen.<environment>.yaml` for named targets such as staging or production

Both YAML and shell configuration support list values. YAML lists are the clearest choice for `STYLES`, scripts, and asset lists.

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
- `DEFER_SCRIPTS` — newline-separated list of JS files to include with `defer`
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
- `INHERIT_CORE_STATIC` — copy `smgen`'s built-in static assets first, then overlay your project `static/` directory (default `0`)
- `CORE_ASSETS` — newline-separated list of built-in `smgen` static assets to copy before overlaying your project `static/` directory
- `SEARCH_ENABLED` — `auto`, `1`, or `0` (default `auto`)
- `SEARCH_READER` — browser search-reader module (default `/vendor/smgen-search/SearchReader.mjs`)
- `NAVIGATION_WIDGETS` — ordered list of sidebar widgets; available values are `tree` and `archive` (default `tree`)
- `ARCHIVE_TITLE` — visible archive-widget heading (default `Archive`)
- `PLUGINS` — ordered list of menu plugin manifests
- `MENUS` — mapping of named menus using the `flat`, `tree`, or `timeline` renderer
- `MENU_REGIONS` — mapping of template region names to ordered menu names

### Executable Locations

- `PHP` — PHP executable (default `php`)
- `PANDOC` — Pandoc executable (default `pandoc`)
- `YQ` — `yq` executable (default `yq`)
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

Hooks run for every build, including rebuilds started by `smgen watch`. The before hook runs before rendering. The after hook runs after the new output has been installed; if it fails, SMGen restores the previous output.

## Output Ownership

SMGen builds into a temporary sibling directory and atomically replaces `OUTPUT_DIR` after a successful render. Files left in an old output disappear on the next successful build. Keep authored assets in `STATIC_DIR`, because `OUTPUT_DIR` is fully managed by SMGen.
