---
title: Getting Started
weight: 0
author:
    - name: Sean Morris
---

# SMGen
*Static Site Generator*

Bash-driven static-site generator that follows the Unix philosophy. Converts Markdown files into a fully templated HTML website using PHP, yq, and Pandoc:

### Requirements

- **Bash** (shell, version >= 4)
- **PHP** (cli & php-yaml)
- **yq** (YAML processor)
- **Pandoc** (Markdown to HTML converter)
- **uuid** (UUID generator)

### Recommended

- **inotify-tools** (Filesystem event watcher)
- **aspell** (SpellChecker)
- **smgen-search** (Search index generator)

```bash
apt install php php-yaml bash pandoc uuid inotify-tools

YQ_VERSION=v4.47.2
wget -qO /usr/local/bin/yq https://github.com/mikefarah/yq/releases/download/${YQ_VERSION}/yq_linux_amd64
chmod +x /usr/local/bin/yq
```

## Install

To install `smgen` (the static site generator) system-wide and set up an update path, run:

```bash
curl -fsSL https://seanmorris.github.io/smgen/install.sh | sudo bash
```

This command clones the repository into `/usr/share/smgen`, creates a `smgen` symlink in `/usr/local/bin`, and can be used to update `smgen` by re-running this script.

## Getting Started

Create a directory for your project and run `smgen init` to set up the folder structure and standard template.

```bash
mkdir my-project/
cd my-project/

smgen init
```

Then open `.smgen-rc` or `.smgen.yaml` and configure your details:

```bash
PRODUCT_NAME="Your Product Name"
TAGLINE="Your Product Tagline"
ORGANIZATION="Your Tagline"
```

Add Markdown files under the `pages/` directory. Each file may start with YAML front-matter:

```markdown
---
title: Home
author:
  - name: Your Name
---

# Welcome to My Site

This is my first page content.
```

Save it as `pages/index.md` (or any path under `pages/`).

Build the project:

```bash
smgen build
```

And kick off the dev server:

```bash
smgen serve
```

Open <http://localhost:8000> in your browser to view the site and see the project running.

On Linux, you can also run `smgen watch` to spin up the dev server and build changes automatically whenever the filesystem is updated:

```bash
smgen watch
```

## Search Integration

Add full-text search to your site with the [smgen-search CLI](https://www.npmjs.com/package/smgen-search). See [Search Integration Example](examples/search.html) for detailed setup instructions.

## Templates

The following files are created by the `init` script and can be modified for customization:

* `templates/page.php`
* `templates/header.php`
* `templates/footer.php`

See [Customization#Themes, CSS, and JS Injection](customization.html#themes-css-and-js-injection) for info on writing or customizing your own styles and JavaScript.

See [Customization#Writing Your Own Templates](customization.html#writing-your-own-templates) for info on creating/editing your own templates.
