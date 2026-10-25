# SMGen

SMGen is a Bash-based static site generator. It renders Markdown with Pandoc,
wraps each page in PHP templates, copies static assets, and writes a complete
HTML site.

<a target="_blank" href="https://seanmorris.github.io/smgen/">
<img width="100%" src="https://seanmorris.github.io/smgen/banner-cropped.jpg" alt="SMGen" />
</a>

SMGen supports:

- YAML frontmatter for pages and directories
- PHP and Pandoc templates
- named menus with flat, tree, and timeline renderers
- ordered menu plugins and alter hooks
- named RSS 2.0 feeds with YAML channel and item data
- sitemap generation
- optional local search through `smgen-search`
- atomic output replacement with rollback after failed builds
- local serving, Linux file watching, and spell checking

## Requirements

Every build requires:

- Bash 4 or newer
- PHP CLI with the YAML extension
- [mikefarah/yq](https://github.com/mikefarah/yq) 4
- Pandoc

The npm installation method requires Node.js 20 or newer.

`smgen init`, `smgen create-random-page`, and the installer also require
`curl`. RSS feed generation requires the PHP XMLWriter extension. Optional
commands use `inotify-tools`, `aspell`, and `smgen-search`.

On Debian or Ubuntu:

```bash
apt install bash php php-yaml php-xml pandoc curl inotify-tools aspell

YQ_VERSION=v4.47.2
wget -qO /usr/local/bin/yq "https://github.com/mikefarah/yq/releases/download/${YQ_VERSION}/yq_linux_amd64"
chmod +x /usr/local/bin/yq
```

## Install

Install SMGen globally with npm:

```bash
npm install --global smgen
```

To pin SMGen inside one site instead, add it as a development dependency and
run it through the local executable:

```bash
npm install --save-dev smgen
npx smgen build
```

npm installs the SMGen command, templates, helpers, and static assets. Install
PHP, Pandoc, `yq`, and any optional shell tools through the operating system.

The shell installer remains available for machines that do not use npm:

```bash
curl -fsSL https://seanmorris.github.io/smgen/install.sh | sudo bash
```

The installer stages a Git checkout under `/usr/share/smgen`, then creates the
`/usr/local/bin/smgen` symlink. It does not replace an existing non-Git
directory.

## Quickstart

Create an empty project directory and initialize it:

```bash
mkdir my-site
cd my-site
smgen init
```

Edit `pages/index.md`, then build and serve the site:

```bash
smgen build
smgen serve
```

Open <http://localhost:8000>. On Linux, `smgen watch` builds the site, starts
the server, and rebuilds after source changes.

## Project Layout

```text
pages/      Markdown and page metadata
templates/  PHP and Pandoc templates
static/     CSS, JavaScript, fonts, images, and other assets
docs/       Generated output by default
.smgen-rc   Shell configuration
.smgen.yaml Declarative YAML configuration
```

SMGen reads configuration from the current working directory. Precedence runs
from built-in defaults through YAML, environment-specific YAML, `.smgen-rc`,
`.env`, and caller-provided environment values.

## Commands

```text
smgen init                Initialize an empty project directory
smgen build [path]        Rebuild the complete site
smgen watch               Build, serve, and watch on Linux
smgen serve               Serve the generated output
smgen proofread [path]    Check Markdown with aspell
smgen create-random-page  Add a generated Markdown page
smgen help                Show command help
```

`smgen build <path>` validates the requested page but still rebuilds every
page. Navigation, RSS feeds, sitemap data, and cross-page metadata therefore
remain consistent. SMGen renders into a temporary sibling directory and
replaces the output only after generation succeeds. If an after-build hook
fails, SMGen restores the previous output.

## Documentation

The hosted documentation is the canonical reference:

- [Getting Started](https://seanmorris.github.io/smgen/index.html)
- [Writing Pages](https://seanmorris.github.io/smgen/writing-pages.html)
- [CLI Reference](https://seanmorris.github.io/smgen/cli.html)
- [Configuration](https://seanmorris.github.io/smgen/configuration.html)
- [Navigation and Plugins](https://seanmorris.github.io/smgen/navigation.html)
- [RSS Feeds](https://seanmorris.github.io/smgen/rss.html)
- [Customization](https://seanmorris.github.io/smgen/customization.html)
- [Deployment](https://seanmorris.github.io/smgen/deployment.html)
- [FAQ and Troubleshooting](https://seanmorris.github.io/smgen/faq.html)

Documentation sources live under `pages/`. Run `./smgen.sh build` to validate
them locally.

## Development

Run the regression suite and documentation build before committing changes:

```bash
bash ./tests/run.sh
./smgen.sh build
```

The regression suite covers configuration precedence, frontmatter, templates,
output rollback, hooks, path safety, static assets, search, sitemap output,
RSS output, legacy navigation, named menus, plugins, and timeline dates.

## License

SMGen is licensed under the Apache License 2.0. See [LICENSE](LICENSE).
