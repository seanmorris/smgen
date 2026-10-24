---
title: Getting Started
weight: 0
author:
  - name: Sean Morris
---

# SMGen

SMGen converts Markdown into a static HTML site. Pandoc renders the content,
PHP templates provide the page structure, and Bash coordinates configuration,
assets, navigation, RSS feeds, search, and sitemap generation.

## Requirements

A build requires:

- Bash 4 or newer
- PHP CLI with the YAML extension
- `yq` 4 from [mikefarah/yq](https://github.com/mikefarah/yq)
- Pandoc

The installer and `smgen init` also require `curl`. Install `inotify-tools` to
use `smgen watch`, `aspell` to use `smgen proofread`, and `smgen-search` to
build a search index.

On Debian or Ubuntu:

```bash
apt install bash php php-yaml pandoc curl inotify-tools aspell

YQ_VERSION=v4.47.2
wget -qO /usr/local/bin/yq "https://github.com/mikefarah/yq/releases/download/${YQ_VERSION}/yq_linux_amd64"
chmod +x /usr/local/bin/yq
```

See the [Linux](os-specific/linux.html) and [macOS](os-specific/mac-osx.html)
pages for platform-specific installation notes.

## Install SMGen

Install or update the command under `/usr/local/bin`:

```bash
curl -fsSL https://seanmorris.github.io/smgen/install.sh | sudo bash
```

The installer stages the Git checkout before replacing `/usr/share/smgen`. It
refuses to replace an existing non-Git directory at that location.

## Create a Site

Initialize an empty directory:

```bash
mkdir my-site
cd my-site
smgen init
```

Set the site identity in `.smgen-rc`:

```bash
PRODUCT_NAME="My Site"
TAGLINE="Project documentation"
ORGANIZATION="Example Organization"
```

Replace `pages/index.md` with your first page:

```markdown
---
title: Home
---

# Welcome

This page was built with SMGen.
```

Build and serve the site:

```bash
smgen build
smgen serve
```

Open <http://localhost:8000>. The generated files are under `docs/` unless you
change `OUTPUT_DIR`.

On Linux, one command can build, serve, and watch the source directories:

```bash
smgen watch
```

## Next Steps

- [Writing Pages](writing-pages.html): page metadata, directory metadata, and output paths
- [CLI Reference](cli.html): every command and its requirements
- [Configuration](configuration.html): precedence, defaults, assets, paths, and search
- [Navigation and Plugins](navigation.html): menus, regions, timelines, and plugins
- [RSS Feeds](rss.html): named feeds, page membership, dates, and output
- [Customization](customization.html): templates, themes, and injected assets
- [Deployment](deployment.html): static hosting and GitHub Pages
