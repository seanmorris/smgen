---
title: llms.txt
weight: 4.75
llms:
  section: guides
  description: Generate a curated machine-readable index of the site's HTML pages.
---

# llms.txt

<!-- <what keywords="llms.txt, ai discovery, machine-readable site index, static agent index"> -->
SMGen can generate `/llms.txt` in the format described by the
[llms.txt proposal](https://llmstxt.org/). The short Markdown index points
readers and agents to selected HTML pages. It contains links and descriptions,
without copied page bodies or browser JavaScript.
<!-- </what> -->

## Enable the Index

<!-- <how keywords="generate llms.txt, configure ai index, llms yaml"> -->
Add `LLMS` to `.smgen.yaml`:

```yaml
LLMS:
  title: Example Documentation
  summary: Build and publish an Example site.
  details: |
    The published site contains static HTML and assets.
  sections:
    start:
      title: Start Here
      weight: 0
    guides:
      title: Guides
      weight: 10
    reference:
      title: Reference
      weight: 20
    optional:
      title: Optional
      weight: 100
```

`title` defaults to `PRODUCT_NAME`. `summary` becomes a blockquote below the
title. `details` accepts Markdown and appears before the first section.

Each section requires a `title`. Its numeric `weight` controls section order;
sections with the same weight retain their YAML order. SMGen omits sections
that have no selected pages.

Set `LLMS: false` or set `enabled: false` inside the mapping to disable
generation. A site without `LLMS` does not produce the file or its HTML
discovery link.
<!-- </how> -->

## Select Pages

<!-- <how keywords="include page in llms.txt, llms frontmatter, group pages for agents"> -->
Select a page through its frontmatter:

```yaml
---
title: Install Example
description-meta: Install the required tools and create a site.
weight: 10
llms:
  section: start
  title: Installation
  description: Install Example and build the first page.
  weight: 0
---
```

`section` must match a key under `LLMS.sections`. The other fields override the
page metadata used by the index:

- `title` falls back to the page's `title`, `pagetitle`, or first level-one
  heading.
- `description` falls back to `description-meta`, then `description`. SMGen
  omits it when none exists.
- `weight` falls back to the page's `weight`, then `0`.

Pages sort by weight and source path. Use a section name as shorthand for
setting only `section`:

```yaml
llms: start
```

Inherited `title`, `description`, and `weight` fields remain in effect.
<!-- </how> -->

## Select a Directory

<!-- <where keywords="directory llms metadata, inherit llms section, exclude inherited page"> -->
Place `llms` in a directory `.fm.yaml` to select its pages and descendants:

```yaml
llms:
  section: guides
```

Nested directory metadata and page frontmatter override inherited `llms`
fields. Set `llms: false` on a directory or page to exclude it. A deeper
directory can select its descendants again.
<!-- </where> -->

## Generated Output

<!-- <what keywords="llms.txt output format, llms links, base url"> -->
A build writes `llms.txt` at the root of `OUTPUT_DIR`:

```markdown
# Example Documentation

> Build and publish an Example site.

## Start Here

- [Installation](https://example.test/docs/install.html): Install Example and build the first page.
```

SMGen derives each link from the generated HTML path and prefixes it with
`BASE_URL`. It percent-encodes path segments such as spaces.

The default page template also adds this discovery link to each HTML head:

```html
<link rel="describedby" type="text/markdown" href="/llms.txt" />
```

Projects initialized with an older SMGen release own an earlier page template.
Add the conditional below inside `<head>` if that template should advertise
the index:

```php
<?php if(llmsEnabled()): ?>
  <link rel="describedby" type="text/markdown"
    href="<?=escapeHtml(resolveAssetUrl('/llms.txt'))?>" />
<?php endif; ?>
```

SMGen validates the configuration and selected pages before replacing the
existing output. A missing section, invalid field, or output collision fails
the build and preserves the previous site.
<!-- </what> -->
