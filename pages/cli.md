---
title: CLI Reference
weight: 2
---

# CLI Reference

Run SMGen from the project root:

```bash
smgen <command>
```

`smgen help` prints the available commands.

## `smgen init`

Initialize an empty directory:

```bash
smgen init
```

The command exits without changing the directory when any file already exists.
It requires `curl` because it downloads the initial Markdown page.

The generated project contains:

```text
docs/
pages/index.md
static/default.css
static/github.svg
static/icon-16.png
static/logo.svg
static/main.js
static/moon.svg
static/search.js
static/search.svg
static/sitemap-badge.png
static/sun.svg
static/vendor/smgen-search/
templates/footer.php
templates/header.php
templates/page.php
.smgen-rc
```

Set `OUTPUT_DIR` in the calling environment to use another output directory
during initialization.

## `smgen build [path]`

Build the complete site:

```bash
smgen build
smgen build pages/guide.md
```

The optional path must resolve inside `PAGES_DIR`, but it does not limit the
build. SMGen always refreshes every page, menu, RSS feed, sitemap entry, and
search record.

SMGen renders into a temporary directory beside `OUTPUT_DIR`. It replaces the
previous output after every page succeeds. A render failure leaves the previous
output in place. If `after-smgen.sh` fails, SMGen restores the previous output.

The output directory cannot be a symbolic link, the project root, or a source
directory. Page templates must resolve inside `TEMPLATE_DIR`, and selected core
assets must resolve inside SMGen's own `static/` directory.

## `smgen watch`

Build the site, start the development server, and rebuild after source changes:

```bash
smgen watch
```

This command requires Linux `inotify-tools`. It watches the configured page,
static, template, and helper directories along with project configuration and
hook files. A failed rebuild leaves the last successful output available.

Set the development port in `.smgen-rc`, `.smgen.yaml`, or the calling
environment:

```bash
DEV_PORT=8080
```

Leave `BASE_URL` empty during local development. SMGen will generate
same-origin URLs such as `/default.css`, so forwarded ports and custom local
host names can load assets from the server that delivered the page.

## `smgen serve`

Serve the current output without watching files:

```bash
smgen serve
```

The command requires an existing `OUTPUT_DIR` and listens on `DEV_PORT`, which
defaults to `8000`.

## `smgen proofread [path]`

Check Markdown with `aspell`:

```bash
smgen proofread
smgen proofread pages/guide.md
```

Without a path, the command checks every Markdown file under `PAGES_DIR` and
reports files that contain unknown words. With a path, it prints the unknown
words from that file.

`ASPELL` selects the executable. `ASPELL_DICT` selects the personal dictionary
and defaults to `aspell.txt`. Its first line must contain a valid header:

```text
personal_ws-1.1 en utf8
```

## `smgen create-random-page`

Create a generated Markdown page under `PAGES_DIR`:

```bash
smgen create-random-page
```

This command requires `curl`, network access, and a readable
`/usr/share/dict/words`. It chooses an unused dictionary word for the filename
and downloads lorem ipsum Markdown for the page body.

## `smgen help`

Show command help:

```bash
smgen help
```
