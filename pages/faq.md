---
title: FAQ and Troubleshooting
weight: 7
llms:
  section: optional
  description: Diagnose common dependency, build, asset, navigation, and deployment problems.
---

# FAQ and Troubleshooting

## Why does SMGen report that a command is required?

Install the named executable and ensure it is on `PATH`. Every build requires
Bash 4 or newer, PHP 8.1 or newer with the YAML extension, and Pandoc. The Bash
compatibility engine also requires mikefarah `yq` 4. RSS feed generation
requires XMLWriter. The `watch`, `proofread`, and search features have
additional optional tools.

## Why does `yq` report a parser or `jq` error?

Several unrelated programs use the name `yq`. The Bash compatibility engine
requires the Go executable
from [mikefarah/yq](https://github.com/mikefarah/yq), version 4. Confirm the
installed program:

```bash
yq --version
```

On Linux, install a pinned release directly when the package manager supplies a
different program:

```bash
YQ_VERSION=v4.47.2
wget -qO /usr/local/bin/yq "https://github.com/mikefarah/yq/releases/download/${YQ_VERSION}/yq_linux_amd64"
chmod +x /usr/local/bin/yq
```

## Why is page frontmatter ignored?

The opening `---` marker must be the first content in the file. The closing
marker must appear on its own line. SMGen accepts Unix and Windows line endings.

```yaml
---
title: Example
---
```

A `---` horizontal rule later in an ordinary Markdown page is valid.

## Why did a build edit my page source?

A page selected into an `smgen-timeline` menu needs `date` or `created`. If
both are absent, the plugin derives a creation time and writes one `created`
field after menu validation succeeds.

SMGen does not generate `modified`.

RSS selection does not trigger this source edit. A page selected into an RSS
feed must supply `date` or `created` in its frontmatter.

## Why does an RSS feed fail to build?

Check that every name under the page's `feeds` field exists in `RSS_FEEDS`.
Each selected page needs a valid `date` or `created` value. Feed output paths
must end in `.xml` and cannot replace a page, static asset, or sitemap file.

An `Unknown RSS namespace prefix` error means custom `rss` or `channel` data
uses a prefix missing from the feed's `namespaces` mapping. Add the namespace
URI to every feed that receives the custom element.

See [RSS Feeds](rss.html) for the configuration and frontmatter formats.

## Why did a failed build leave the old site in place?

SMGen renders the complete site in a temporary directory. It replaces
`OUTPUT_DIR` only after every page succeeds. A failed render or validation step
leaves the previous site available. A failed `after-smgen.sh` hook restores the
previous output.

## Why is custom CSS or JavaScript missing?

Confirm that the source file is under `STATIC_DIR`, then check `STYLES`,
`DEFER_SCRIPTS`, `BODY_SCRIPTS`, or the corresponding inline setting. A
root-relative URL is joined to `BASE_URL`.

Leave `BASE_URL` empty in development. An absolute local value such as
`http://localhost:8000` sends asset requests to that exact browser-visible
origin, which fails when a container, virtual machine, or port-forwarding
service exposes the development server under another host name.

A successful build replaces the complete output. Files written directly to
`OUTPUT_DIR` disappear unless they also exist in a source directory or a build
hook recreates them.

## Why does a menu fail to build?

SMGen validates menu names, fields, renderers, link targets, parents, cycles,
regions, and renderer results. Read the first error printed by the build. The
message includes the menu, provider, page, or field that failed validation.

Common causes include:

- a region names an undefined menu
- a link defines more than one of `page`, `href`, or `group`
- a parent ID does not exist in the same menu
- a plugin renderer rejects a link, such as a timeline holding a group
- a page names a menu that has no definition

See [Navigation and Plugins](navigation.html) for the complete schema.

## Why is search missing or stale?

Install the pinned indexer and enable search:

```bash
npm install --global smgen-search@0.1.1
SEARCH_ENABLED=1 smgen build
```

With `SEARCH_ENABLED=1`, a build fails when neither the indexer nor a prebuilt
`static/search.bin` exists. With `SEARCH_ENABLED=auto`, SMGen disables search
when neither input exists.

The browser loads the reader and binary index after the search field receives
focus or a query.

## Why is `smgen watch` unavailable on macOS?

The watcher uses Linux `inotify-tools`. On macOS, run `smgen serve` in one
terminal and run `smgen build` after changes in another.

## How do I install the PHP YAML extension?

Linux distributions usually provide a `php-yaml` package. On macOS, install a
matching PHP and YAML extension through shivammathur's Homebrew taps. See the
[macOS installation page](os-specific/mac-osx.html) for commands.

## How do I install XMLWriter?

Debian, Ubuntu, and Fedora provide XMLWriter in the `php-xml` package.
Homebrew PHP includes it. Confirm the active PHP installation with
`php -m | grep -i '^xmlwriter$'`.

## Why does `smgen proofread` reject my dictionary?

`ASPELL_DICT` defaults to `aspell.txt`. Its first line must declare the personal
dictionary format and encoding:

```text
personal_ws-1.1 en utf8
```

Add one accepted word per line after the header.
