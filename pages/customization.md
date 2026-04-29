---
title: Customization
weight: 3
---

# Customization

Customize your site appearance and behavior:

## Themes, CSS, and JS Injection

Use front-matter or `.smgen-rc` to add custom styles and scripts. For example, if you have files under `static/`:

```css
/* static/example.css */
.active-link {
    color: #d33;
    text-decoration: underline;
}
```

```js
// static/example.js
document.addEventListener('DOMContentLoaded', function() {
    console.log('Example.js loaded');
});
```

Then inject them into your build via `.smgen-rc`:

```bash
STYLES=$(cat <<-END
    /example.css
END
)

SCRIPTS=$(cat <<-END
    /example.js
END
)
```

These will insert `<link rel="stylesheet" href="/example.css">` in the header and `<script src="/example.js"></script>` before `</body>`.

To inline files directly in the HTML (rather than referencing them), use the `INLINE_STYLES` and `INLINE_SCRIPTS` variables:

```bash
INLINE_STYLES=$(cat <<-END
    /inline.css
END
)

INLINE_SCRIPTS=$(cat <<-END
    /inline.js
END
)
```

With these set, the contents of `/inline.css` will be embedded inside a `<style>` tag in the `<head>`, and `/inline.js` contents will be embedded inside a `<script>` tag before `</body>`.

### CSS Theming

SMGen's default stylesheet (`static/default.css`) defines a set of CSS custom properties
(variables) for colors, spacing, and other design tokens in `:root` and `:root.dark`
near the bottom of the file. You can override any of these values by loading a
custom theme CSS after the default styles.

1. Create a theme file (e.g. `static/theme.css`) and redefine variables:

```css
:root.theme-cosmic {
	/* Core palette */
	--bg-0: #0A0E20;   /* deep space navy */
	--bg-1: #1F0740;   /* cosmic plum */
	--bg-2: #020D1B;   /* near-black blue */

	--background: var(--bg-0) url('./bg.jpg') no-repeat center / cover fixed;

	--fg-0: #EAF2FF;   /* primary text on dark */
	--fg-1: #B9C7FF;   /* secondary text */
	--muted: #8CA0B3;  /* subtle labels */

	/* UI surface tokens */
	--surface-0: color-mix(in srgb, color-mix(in oklab, var(--acc-violet)  15%, #000000), transparent 30%);
	--surface-1: color-mix(in srgb, color-mix(in oklab, var(--acc-magenta) 30%, #000000), transparent 40%);
	--surface-2: color-mix(in srgb, color-mix(in oklab, var(--acc-gold)    20%, #000000), transparent 40%);
	--surface-3: color-mix(in srgb, color-mix(in oklab, var(--acc-gold)    20%, #F3F6FF), transparent 25%);

	--border: color-mix(in oklab, var(--fg-1) 15%, transparent);
	--shadow: 0 10px 30px color-mix(in oklab, black 70%, transparent);
	--ring: color-mix(in oklab, var(--acc-cyan) 60%, white 10%);

	/* Semantic */
	--primary: var(--acc-cyan);
	--primary-contrast: #041018;
	--link: var(--acc-gold);
	--success: #43E6A2;
	--warning: var(--acc-gold);
	--danger:  #FF516D;
}
```

2. Include your theme file after the default stylesheet in `.smgen-rc` (or front‑matter):

```bash
STYLES=$(cat <<-END
  /default.css
  /theme.css
END
)
```

Inject that snippet via `INLINE_SCRIPTS` or place it directly in your `templates/header.php`.

### Syntax Highlighting

You can set the syntax highlighting theme using the `HIGHLIGHT_STYLE` variable in .smgen-rc:

```bash
HIGHLIGHT_STYLE=zenburn
```

You can list the available syntax highlighters with `pandoc --list-highlight-styles`:

```bash
pandoc --list-highlight-styles
# pygments
# tango
# espresso
# zenburn
# kate
# monochrome
# breezedark
# haddock
```

## Writing Your Own Templates

This project uses PHP+Pandoc templates to wrap your Markdown content in HTML. Templates are a blend of Pandoc template variables (e.g. `$body$`, `$title$`, `$pagetitle$`, `$styles.html()$`, `$for(css)$…`) and PHP code. To create a custom template:

1. Copy the default `templates/page.php` to a new file (e.g. `templates/my-page.php`).
2. Modify the HTML and Pandoc placeholders as needed.
3. In your page's YAML front-matter, set the `template` field. For example:

   ```markdown
   ---
   title: Custom Template Example
   template: templates/my-page.php
   ---

   # My Page Heading
   This page uses the custom template specified above.
   ```

Pandoc will use your custom template when rendering that page. You can also create extension-specific templates named `<ext>-page.php` (or `.html`) in the `templates/` directory and the build script will pick them up automatically.

Below is a minimal example:

```php
<?php
// templates/my-page.php
?>
<!DOCTYPE html>
<html>
<head>
  <title>$if(pagetitle)$${pagetitle}$else$${title}$endif$</title>
  $for(css)$<link rel="stylesheet" href="$css$" />$endfor$
  $styles.html()$
</head>
<body>
  <?php include 'navbar.php'; ?>
  $body$
</body>
</html>
```

## Front-matter Fields

Leverage custom fields in YAML front-matter to pass variables into templates.

## Custom Templates per Extension

Place extension-specific templates:

- `templates/md-page.php`
- `templates/html-template.html`

## Template Fallback Logic

The build script selects templates based on front-matter or file extension:

1. Front-matter field `template`.
2. Default `templates/page.php`.
3. Extension-specific `templates/<ext>-page.php` or `.html`.

## Navigation Customization

Use YAML front-matter fields in pages or directory-level `.fm.yaml` files to control the left sidebar navigation. The helper script reads these fields when building the nav bar:

- `weight` — numeric weight for ordering pages and sections (lower values appear first).
- `title` — override the link text for a page or section heading.
- `leftBarLink` — set to `false` to omit a specific page or directory from the nav.
- `leftBarShow` — set to `false` in a page's front-matter to hide the entire sidebar on that page.

### Examples

Front-matter in an individual page (`foo.md`):

```yaml
---
title: "My FAQ"
weight: 100
leftBarLink: false
leftBarShow: false
---
```

Directory-level settings (`pages/guides/.fm.yaml`):

```yaml
title: "Guides & Tutorials"
weight: 50
leftBarLink: true
```

## HTML Partial Includes

Use PHP includes inside templates for reusable markup snippets.

## Table of Contents Settings

Control the TOC with front-matter `TOC` (true/false) or omit to let default TOC injection.

```yaml
TOC: false
```
