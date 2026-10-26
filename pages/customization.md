---
title: Customization
weight: 5
llms:
  section: guides
  description: Add assets, change templates and themes, or override build helpers.
---

# Customization

SMGen separates authored assets, templates, and helpers from generated output.
Keep custom files under `static/`, `templates/`, or `helpers/` so each complete
build can replace `OUTPUT_DIR` safely.

## Add Styles and Scripts

<!-- <how keywords="custom css and javascript, publish static assets, watch static changes, link stylesheet, script settings"> -->
Place browser assets under `static/`, then add their published URLs to
`.smgen.yaml`:

```yaml
STYLES:
  - /default.css
  - /theme.css
DEFER_SCRIPTS:
  - /main.js
BODY_SCRIPTS:
  - /analytics.js
```

Every build copies the complete `STATIC_DIR` tree. `smgen watch` monitors that
directory and runs a complete build after a static file changes. Copying a CSS
file publishes it, while `STYLES` tells the page template to link it.

`SCRIPTS` emits blocking scripts in the document head. Use `DEFER_SCRIPTS` for
scripts that do not need to block HTML parsing. `BODY_SCRIPTS` emits scripts
before the closing body tag.

Inline local files when an extra request would cost more than the added HTML:

```yaml
INLINE_STYLES:
  - static/critical.css
INLINE_SCRIPTS:
  - static/critical.js
INLINE_BODY_SCRIPTS:
  - static/after-content.js
```

Inline settings read the named file during the build. URL-based settings pass
through `BASE_URL` unless they already contain an absolute URL, protocol,
data URL, email link, or telephone link.
<!-- </how> -->

## Use Built-in Assets

A project initialized by SMGen owns copies of the standard templates and
assets. Existing projects can consume later core assets in either of two ways.

Copy the complete built-in asset tree before applying project overrides:

```yaml
INHERIT_CORE_STATIC: 1
```

Copy selected files only:

```yaml
CORE_ASSETS:
  - default.css
  - main.js
  - search.svg
```

Project files under `STATIC_DIR` always copy last, so a file with the same path
overrides the built-in version. `CORE_ASSETS` takes precedence over
`INHERIT_CORE_STATIC`.

## Create a Theme

The default stylesheet exposes color and layout values as CSS custom
properties. Load a theme after `default.css` and override the values you need:

```css
:root.theme-project {
  --background: #10131a;
  --fg-0: #f4f7ff;
  --surface-0: #1a2030;
  --link: #8fc7ff;
  --primary: #67e0ba;
}
```

```yaml
DEFAULT_THEME: theme-project
STYLES:
  - /default.css
  - /theme.css
```

Use `pandoc --list-highlight-styles` to list accepted values for
`HIGHLIGHT_STYLE`.

## Write a Page Template

<!-- <how keywords="copy default template, change page layout, select template in frontmatter, custom html layout"> -->
A page template combines PHP with Pandoc template variables. Copy
`templates/page.php` when you need a different document structure, then select
the new file from frontmatter:

```yaml
---
title: Product Guide
template: templates/product-page.php
---
```

A minimal template can render named regions around Pandoc's page body:

```php
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>$if(pagetitle)$${pagetitle}$else$${title}$endif$</title>
  $for(css)$<link rel="stylesheet" href="$css$" />$endfor$
  $styles.html()$
  <?php renderJsonLd($frontmatter, $pageFile); ?>
</head>
<body>
  <?php if (menuRegionExists('header')): ?>
    <header><?php renderMenuRegion('header'); ?></header>
  <?php endif; ?>
  <main>
    <?php if (menuRegionExists('sidebar')): ?>
      <aside><?php renderMenuRegion('sidebar'); ?></aside>
    <?php endif; ?>
    <article>$body$</article>
  </main>
</body>
</html>
```

The template path must resolve inside `TEMPLATE_DIR`. SMGen selects a page
template in this order:

1. the page's `template` field
2. `templates/<source-extension>-page.smgen.php`
3. `templates/<source-extension>-page.php`
4. `templates/<source-extension>-template.html`
5. `templates/page.smgen.php`
6. `templates/page.php`

For a Markdown source, the extension-specific names are `md-page.php` and
`md-template.html`.

Keep the `renderJsonLd` call when a custom template should emit the page's
`jsonld` frontmatter.
<!-- </how> -->

## Reuse a Template Process

SMGen runs existing `.php` page templates in a separate PHP process for each
page. This preserves templates that declare functions or classes and prevents
one page from changing another page's PHP state.

A filename ending in `.smgen.php` opts into the reusable renderer. The file
loads once per build and returns a callable:

```php
<?php

use Smgen\Template\PageContext;

return static function(PageContext $page): void {
  ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>$if(pagetitle)$${pagetitle}$else$${title}$endif$</title>
</head>
<body data-page="<?=$page->escape($page->currentPage())?>">
  <article>$body$</article>
</body>
</html>
  <?php
};
```

`PageContext` provides `config()`, `frontmatter()`, `metadata()`, source and
destination paths, `assetUrl()`, `escape()`, JSON-LD output, menu rendering,
and RSS and `llms.txt` discovery. Use `renderFragment()` for reusable header
and footer providers inside `TEMPLATE_DIR`.

The callable receives a new context for each page. Do not keep page data in
static variables or mutate shared globals. A provider may emit its template or
return it as a string, but it cannot do both.

Initialize a site with reusable default providers when starting a new template:

```bash
smgen init --reusable-templates
```

Existing projects can keep `.php` templates. The PHP build engine supports both
modes in the same site.

## Change Header and Footer Includes

Set project-wide include defaults through `HEADER` and `FOOTER`, or select them
for one page with frontmatter:

```yaml
header: templates/product-header.php
footer: templates/product-footer.php
```

Both paths must resolve inside `TEMPLATE_DIR`. A reusable page provider must
render `.smgen.php` header and footer providers.

## Override Helpers

Set `HELPERS_DIR` to a project helper directory. SMGen checks it for
`domain.lua`, `llms.php`, `navigation-data.php`, `rss.php`, and `sitemap.php`
before using the built-in versions. A project `navbar.php` may define a legacy
`renderNavBar()` while the core named-menu functions remain available.

Helper overrides execute as part of the build and receive the same environment
as their built-in counterparts. Keep their output deterministic so a complete
rebuild produces the same site from the same sources.

## Configure Navigation

Define menus and regions in YAML, then render them from templates. The
[Navigation and Plugins](navigation.html) page documents menu fields, page
membership, timelines, plugin selectors, and alter hooks.

The original `renderNavBar()`, `leftBarLink`, `leftBarShow`,
`NAVIGATION_WIDGETS`, and `ARCHIVE_TITLE` interfaces remain available for older
projects.
