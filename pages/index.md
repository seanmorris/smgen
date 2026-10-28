---
title: Getting Started
weight: 0
llms:
  section: start
  description: Install SMGen, create a site, and build its first page.
author:
  - name: Sean Morris
jsonld:
  "@context": https://schema.org
  "@graph":
    - "@type": WebSite
      "@id": "https://seanmorris.github.io/smgen/#website"
      url: https://seanmorris.github.io/smgen/
      name: SMGen
      description: Build static sites from Markdown with Pandoc, PHP templates, and versioned plugins.
      inLanguage: en
      author:
        "@id": "https://seanmorris.github.io/smgen/#author"
    - "@type": WebPage
      "@id": "https://seanmorris.github.io/smgen/#webpage"
      url: https://seanmorris.github.io/smgen/
      name: Getting Started
      description: Build static sites from Markdown with Pandoc, PHP templates, and versioned plugins.
      inLanguage: en
      isPartOf:
        "@id": "https://seanmorris.github.io/smgen/#website"
      about:
        "@id": "https://seanmorris.github.io/smgen/#software"
      mainEntity:
        "@id": "https://seanmorris.github.io/smgen/#software"
      author:
        "@id": "https://seanmorris.github.io/smgen/#author"
    - "@type": SoftwareSourceCode
      "@id": "https://seanmorris.github.io/smgen/#software"
      url: https://seanmorris.github.io/smgen/
      name: SMGen
      description: Build static sites from Markdown with Pandoc, PHP templates, and versioned plugins.
      codeRepository: https://github.com/seanmorris/smgen
      license: https://www.apache.org/licenses/LICENSE-2.0
      programmingLanguage:
        - Bash
        - PHP
      author:
        "@id": "https://seanmorris.github.io/smgen/#author"
    - "@type": Person
      "@id": "https://seanmorris.github.io/smgen/#author"
      name: Sean Morris
---

# SMGen

<!-- <what keywords="static site generator, purpose, architecture"> -->
SMGen turns Markdown into portable static HTML files. You can serve the output
with GitHub Pages or any other static host, and the deployed site does not need
PHP or an application server. Pandoc renders the content, PHP templates provide
the page structure, and the PHP build engine coordinates configuration, assets,
navigation, plugins, RSS feeds, search, and sitemap generation. A thin Bash
entrypoint retains the command-line, watch, serve, and compatibility commands.
<!-- </what> -->

## Requirements

A build requires:

- Bash 4 or newer
- PHP 8.1 or newer with the YAML extension
- Pandoc
- `xargs` from findutils, preinstalled on Linux and macOS

The npm installation method requires Node.js 20 or newer.

The Bash compatibility engine requires `yq` 4 from
[mikefarah/yq](https://github.com/mikefarah/yq). The installer and `smgen init`
also require `curl`. RSS feed generation requires the PHP XMLWriter extension.
Install `inotify-tools` for the watch command, `aspell` for proofreading, and
`smgen-search` to build a search index.

On Debian or Ubuntu:

```bash
apt install bash php php-yaml php-xml pandoc curl inotify-tools aspell

YQ_VERSION=v4.47.2
wget -qO /usr/local/bin/yq "https://github.com/mikefarah/yq/releases/download/${YQ_VERSION}/yq_linux_amd64"
chmod +x /usr/local/bin/yq
```

See the [Linux](os-specific/linux.html) and [macOS](os-specific/mac-osx.html)
pages for platform-specific installation notes.

## Install SMGen

Install SMGen globally with npm:

```bash
npm install --global smgen
```

To pin SMGen inside one site, install it as a development dependency:

```bash
npm install --save-dev smgen
npx smgen build
```

npm installs the command and its runtime files. Install PHP, Pandoc, and
optional shell tools through the operating system. Install `yq` when a project
uses the Bash compatibility engine.

The shell installer remains available for machines that do not use npm:

```bash
curl -fsSL https://seanmorris.github.io/smgen/install.sh | sudo bash
```

The installer stages the Git checkout before replacing `/usr/share/smgen`. It
refuses to replace an existing non-Git directory at that location.

## Create a Site

<!-- <how keywords="first site, start, setup, initialize, local site, commands and files, from nothing, empty folder"> -->
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
<!-- </how> -->

## Next Steps

- [Writing Pages](writing-pages.html): page metadata, directory metadata, and output paths
- [CLI Reference](cli.html): every command and its requirements
- [Configuration](configuration.html): precedence, defaults, assets, paths, and search
- [Navigation and Plugins](navigation.html): menus, regions, renderers, and plugins
- [Build Plugins](plugins.html): selected assets, generated pages, filters, and build hooks
- [RSS Feeds](rss.html): named feeds, page membership, dates, and output
- [llms.txt](llms.html): a curated machine-readable index of generated pages
- [Customization](customization.html): templates, themes, and injected assets
- [Deployment](deployment.html): static hosting and GitHub Pages
