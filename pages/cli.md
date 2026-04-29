---
title: CLI Reference
weight: 1
---

# CLI Reference

Use the `smgen` command to manage your static site. The basic usage is:

```bash
smgen <command>
```

You can also get help via:

```bash
smgen help
```
## smgen init

Initialize a new site in current directory. **This command will fail if run in a non-empty directory.**

```bash
smgen init
```

This command will create the following folders in the current directory:

```bash
docs/ # override with OUTPUT_DIR env var
pages/
static/
templates/
```

It will also create the following files:

```bash
.smgen-rc
static/main.js
static/default.css
templates/page.php
templates/header.php
templates/footer.php
pages/index.md
```

The `pages/index.md` file will contain random lipsum markdown.

## smgen build

Build the site from Markdown to HTML.

```bash
smgen build
smgen build <file>
```

## smgen watch

Start a dev server, build pages, and copy assets on filesystem changes.

**Requires inotify-tools.**

```bash
smgen watch
```

You can configure the port used using DEV_PORT in .smgen-rc:

```bash
DEV_PORT=8080 # Default 8000
```

## smgen serve

Serve the site locally without running the file watcher.

```bash
smgen serve
```

`DEV_PORT` also applies here.

## smgen proofread

Proofread your markdown to catch spelling errors.

```bash
smgen proofread
smgen proofread <file>
```

Running `smgen proofread` without a parameter will list all the files with unknown/misspelled words in the `pages/` directory.

Running `smgen proofread <file>` will list the unknown misspelled words from that file.

From there you can correct any spelling errors, and/or add new words to your dictionary file: `./aspell.txt` in the root of the project.

Words should be separated by a newline. The first line should be `personal_ws-1.1 en utf8` or similar.

Words that appear in the dictionary file will not be considered misspelled.

The dictionary file can be configured with the `ASPELL_DICT` variable.

## smgen create-random-page

Create a random lorem ipsum markdown page.

```bash
smgen create-random-page
```

## smgen help

Show the help message.

```bash
smgen help
```
