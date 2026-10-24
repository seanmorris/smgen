---
title: RSS Feeds
weight: 4.5
---

# RSS Feeds

SMGen can generate one or more named RSS 2.0 feeds during a site build. Feed
membership comes from page frontmatter. Sites without `RSS_FEEDS` produce no
feed files or discovery links.

## Define Feeds

Add `RSS_FEEDS` to `.smgen.yaml` or an environment-specific YAML file:

```yaml
RSS_FEEDS:
  main:
    path: /rss.xml
    title: Example Site
    description: Recent articles and notes
    link: /
    language: en-us
    limit: 20
  releases:
    path: /feeds/releases.xml
    title: Product Releases
    description: Product release announcements
    link: /releases.html
    limit: 0
```

Each feed supports:

- `title`: required channel title.
- `description`: required channel description.
- `path`: output path under `OUTPUT_DIR`. Default: `/<feed-name>.xml`.
- `link`: channel page URL. Local paths resolve against `BASE_URL`. Default: `/`.
- `language`: optional channel language such as `en-us`.
- `limit`: maximum number of items. Default: `20`. Use `0` for every item.
- `namespaces`: XML namespace prefixes used by custom feed data.
- `channel`: custom elements added to the RSS channel.

Feed names may contain letters, numbers, periods, underscores, and hyphens.
Each output path must end in `.xml`. Duplicate paths and paths that collide
with another generated or static file fail the build.

Set `BASE_URL` for production builds so channel, item, and discovery URLs are
absolute. An empty development value produces root-relative URLs.

## Select Pages

Use a list when a page belongs to each named feed:

```yaml
---
title: Version 2.0
date: 2026-10-24T12:30:00Z
feeds: [main, releases]
---
```

Use a boolean mapping when membership is conditional or easier to scan by
name:

```yaml
feeds:
  main: true
  releases: false
```

An unknown feed name or a membership value other than `true` or `false` fails
the build. Pages without `feeds` do not appear in any RSS feed.

## Add Custom RSS Data

Use `channel` in a feed definition for channel data. Use `rss` in page
frontmatter for item data:

```yaml
RSS_FEEDS:
  main:
    title: Example Site
    description: Recent articles
    namespaces:
      media: http://search.yahoo.com/mrss/
    channel:
      copyright: Copyright 2026 Example Site
      category: [Engineering, Releases]
```

```yaml
---
title: Version 2.0
date: 2026-10-24
feeds: [main]
rss:
  author: editor@example.test
  category:
    - _text: Releases
      _attributes:
        domain: https://example.test/topics
  enclosure:
    _attributes:
      url: https://example.test/version-2.mp3
      length: 1234
      type: audio/mpeg
  media:content:
    _attributes:
      url: https://example.test/version-2.png
      medium: image
---
```

A scalar becomes element text. A sequence repeats the element. A mapping
creates child elements. Within an element, `_text` sets its text and
`_attributes` sets XML attributes. An element may contain text or child
elements, but not both. A null value creates an empty element.

Declare each prefix used by custom elements or attributes in the feed's
`namespaces` mapping. SMGen declares `atom` and `xml` itself. Custom values are
escaped and emitted as written; URL values do not resolve against `BASE_URL`.
Generated channel and item fields cannot be replaced through custom data.

## Dates and Item Data

SMGen chooses an item's publication time in this order:

1. `date`
2. `created`

RSS generation never reads filesystem or Git times and never edits a source
page. A selected page without either field fails the build. `modified` remains
an optional sitemap value and does not change RSS publication order.

Items sort from newest to oldest. Items with the same timestamp sort by URL.
Each item contains a title, link, permalink GUID, and RSS publication date.
`description-meta` supplies an optional item description. The title falls back
from `title` to `pagetitle`, then to the source filename.

The default template adds one discovery link for each configured feed:

```html
<link rel="alternate" type="application/rss+xml"
  title="Example Site" href="/rss.xml" />
```

Custom page templates can render the same links from `rssFeedDefinitions()`.
Feed generation adds no stylesheet, browser script, or client-side work.
