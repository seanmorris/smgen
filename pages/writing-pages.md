---
title: Writing Pages
weight: 1
---

# Writing Pages

Place source pages under `PAGES_DIR`, which defaults to `pages/`. A source path
keeps its directory structure and receives an `.html` extension:

```text
pages/index.md          -> docs/index.html
pages/guides/install.md -> docs/guides/install.html
```

Run `smgen build` after an edit. On Linux, `smgen watch` rebuilds when a source,
template, helper, asset, configuration, or hook file changes.

## Page Frontmatter

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
menus:
  documentation: true
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
- `menus`: named menu memberships and per-menu link settings.

The default template also uses Pandoc metadata such as `pagetitle`, `author`,
`description-meta`, and `canonical`. Custom templates may read any frontmatter
field that Pandoc exposes.

Paths in `template`, `header`, and `footer` must resolve inside `TEMPLATE_DIR`.
A path that escapes that directory fails the build before output replacement.

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

## Timeline and Sitemap Dates

A timeline menu uses `date`, then `created`. When a selected page has neither,
SMGen derives a creation time and writes it to the page as `created`. The build
checks filesystem birth time, first Git addition time, and filesystem
modification time in that order.

SMGen never generates `modified`. When present, it supplies the sitemap
`lastmod` date. When absent, the sitemap uses the source file's modification
time.

Legacy archive membership requires `date`. A page selected explicitly into a
named timeline may use `created` or the generated fallback instead.

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
```

Directory fields include:

- `title`: group label. Default: directory name.
- `weight`: numeric group order.
- `open`: initial expansion state. Default: `true`.
- `leftBarLink`: include the directory and its descendants in legacy navigation.
- `menus`: named flat or tree menus that receive the directory as a group.

A `.fm.yaml` file may contain a plain YAML mapping or use page-style opening and
closing `---` markers.
