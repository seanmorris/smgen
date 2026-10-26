---
title: Writing Pages
weight: 1
llms:
  section: start
  description: Write Markdown pages and configure page or directory frontmatter.
---

# Writing Pages

<!-- <where keywords="new markdown page, source page location, published url, output path"> -->
<!-- <how keywords="add markdown page, create page, preserve directory structure"> -->
Place source pages under `PAGES_DIR`, which defaults to `pages/`. A source path
keeps its directory structure and receives an `.html` extension:

```text
pages/index.md          -> docs/index.html
pages/guides/install.md -> docs/guides/install.html
```

Run `smgen build` after an edit. On Linux, `smgen watch` rebuilds when a source,
template, helper, asset, configuration, or hook file changes.
<!-- </how> -->
<!-- </where> -->

## Page Frontmatter

<!-- <what keywords="page title, yaml frontmatter, page metadata"> -->
<!-- <how keywords="add title, configure page, frontmatter markers"> -->
A page may begin with a YAML mapping enclosed by `---` lines. The opening marker
must be the first content in the file.

```yaml
---
title: Install SMGen
pagetitle: Installation Guide
description-meta: Install SMGen and create a project.
canonical: https://example.com/guides/install.html
author:
  - name: Example Author
weight: 10
template: templates/page.php
TOC: true
header: templates/header.php
footer: templates/footer.php
noSearch: false
leftBarLink: true
leftBarShow: true
date: 2026-10-21
created: 2026-10-20T15:30:00Z
modified: 2026-10-22T09:00:00Z
archive: true
feeds: [main, releases]
menus:
  documentation: true
llms:
  section: guides
---
```

SMGen-specific page fields include:

- `title`: content title and default navigation label.
- `weight`: numeric order used by legacy navigation and source hierarchy groups.
- `template`: page template path inside `TEMPLATE_DIR`.
- `header`: header include path inside `TEMPLATE_DIR`.
- `footer`: footer include path inside `TEMPLATE_DIR`.
- `TOC` or `toc`: enable or disable Pandoc's table of contents. Default: `true`.
- `noSearch`: exclude the page from a generated `smgen-search` index.
- `leftBarLink`: include the page in legacy sidebar inference. Default: `true`.
- `leftBarShow`: render the sidebar on this page. Default: `true`.
- `date`: preferred publication date for a timeline menu.
- `created`: creation date used when `date` is absent.
- `modified`: optional override for sitemap `lastmod`.
- `archive`: include a dated page in the legacy archive. Default: `true`.
- `feeds`: named RSS feed memberships as a list or boolean mapping.
- `rss`: custom RSS item elements for pages selected into a feed.
- `menus`: named menu memberships and per-menu link settings.
- `jsonld`: JSON-LD structured data emitted by the default template.
- `llms`: membership and link metadata for the generated `llms.txt` index.

The default template also uses Pandoc metadata such as `pagetitle`, `author`,
`description-meta`, and `canonical`. Custom templates may read any frontmatter
field that Pandoc exposes.

Paths in `template`, `header`, and `footer` must resolve inside `TEMPLATE_DIR`.
A path that escapes that directory fails the build before output replacement.
<!-- </how> -->
<!-- </what> -->

## JSON-LD

<!-- <what keywords="json-ld, schema.org, structured data, metadata script"> -->
<!-- <how keywords="add json-ld, add schema.org metadata, jsonld frontmatter, rich results"> -->
Set `jsonld` to a YAML mapping to add structured data to a page:

```yaml
---
title: Install SMGen
jsonld:
  "@context": https://schema.org
  "@type": TechArticle
  headline: Install SMGen
  description: Install SMGen and create a project.
  author:
    "@type": Person
    name: Example Author
---
```

The default template encodes the supplied mapping as one minified
`application/ld+json` script in the document head. SMGen does not derive JSON-LD
properties from other frontmatter. Keep the structured data consistent with the
page's visible content.

Use a YAML list to describe multiple top-level entities. SMGen writes the list
as one JSON array in one script:

```yaml
jsonld:
  - "@context": https://schema.org
    "@type": WebSite
    name: Example Site
  - "@context": https://schema.org
    "@type": Organization
    name: Example Organization
```

`jsonld` applies only to the page that declares it. Directory `.fm.yaml` files
do not supply or inherit it. SMGen checks that the top level is a mapping or a
list of mappings, but it does not validate [schema.org](https://schema.org/)
types or required properties. Check a generated page with Google's
[Rich Results Test](https://search.google.com/test/rich-results) and consult the
[JSON-LD 1.1 specification](https://www.w3.org/TR/json-ld11/) for the data model.

The default template still supports the older `itemtype` and flat `microdata`
fields. Omit them when `jsonld` describes the same entity. Existing pages may
continue using them.

Custom page templates must call the renderer inside `<head>`:

```php
<?php renderJsonLd($frontmatter, $pageFile); ?>
```

Projects created before SMGen 0.2.0 own an earlier copy of `templates/page.php`.
Add the renderer call to that template before using `jsonld`.
<!-- </how> -->
<!-- </what> -->

## Menu Membership

Use a list when a page needs membership without custom link settings:

```yaml
menus: [documentation, archive]
```

Use a mapping to configure individual links:

```yaml
menus:
  primary:
    title: Docs
    weight: 20
  documentation:
    parent: group:getting-started
    expanded: true
    description: Read the installation guide
  archive: true
```

Per-menu fields are `title`, `parent`, `weight`, `expanded`, `variant`, `icon`,
and `description`. Set a membership to `false` to remove a page selected by a
plugin. When `menus` exists, SMGen does not infer legacy sidebar or archive
membership for that page.

See [Navigation and Plugins](navigation.html) for menu definitions, link IDs,
regions, plugins, and renderers.

## Publication and Sitemap Dates

A timeline menu uses `date`, then `created`. When a selected page has neither,
SMGen derives a creation time and writes it to the page as `created`. The build
checks filesystem birth time, first Git addition time, and filesystem
modification time in that order.

SMGen never generates `modified`. When present, it supplies the sitemap
`lastmod` date. When absent, the sitemap uses the source file's modification
time.

Legacy archive membership requires `date`. A page selected explicitly into a
named timeline may use `created` or the generated fallback instead.

RSS feeds also use `date`, then `created`. An RSS selection never derives or
writes a timestamp. A selected page without either field fails the build.

## Markdown

Pandoc reads page bodies as Markdown. Its syntax supports headings, lists,
links, tables, fenced code blocks, raw HTML, and other extensions used by the
configured input format.

````markdown
# Installation

Install the required packages, then run:

```bash
smgen build
```
````

See the [Pandoc Markdown manual](https://pandoc.org/MANUAL.html#pandocs-markdown)
for the complete syntax.

## Directory Metadata

Place `.fm.yaml` inside a source directory to configure its navigation group:

```yaml
title: Guides
weight: 20
open: true
leftBarLink: true
menus:
  documentation:
    title: User Guides
    weight: 10
llms:
  section: guides
```

Directory fields include:

- `title`: group label. Default: directory name.
- `weight`: numeric group order.
- `open`: initial expansion state. Default: `true`.
- `leftBarLink`: include the directory and its descendants in legacy navigation.
- `menus`: named flat or tree menus that receive the directory as a group.
- `llms`: inherited `llms.txt` section and link defaults for descendants.

A `.fm.yaml` file may contain a plain YAML mapping or use page-style opening and
closing `---` markers.

See [llms.txt](llms.html) for site configuration, inherited selection, and
field precedence.
