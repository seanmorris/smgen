---
title: Build Plugins
weight: 5.5
llms:
  section: guides
  description: Add selected assets, page resources, generated pages, and build hooks through versioned plugins.
---

# Build Plugins

SMGen plugins package build behavior with a site or npm package. A plugin can
add styles, scripts, Pandoc filters, static assets, generated pages, and trusted
build hooks. Existing menu plugin manifests remain supported.

## Activate a Plugin

<!-- <how keywords="install plugin, enable plugin, npm plugin, local plugin manifest"> -->
Add a manifest path or installed package name to `PLUGINS`:

```yaml
PLUGINS:
  - ./plugins/reading-time/smgen.plugin.yaml
  - smgen-assistant
```

SMGen resolves paths from the project root. For a package name, it searches
local `node_modules` directories. A package can set
`{"smgen":{"plugin":"path/to/manifest.yaml"}}` in `package.json`. The default
package entry is `smgen.plugin.yaml`.

Use a mapping when the plugin accepts options or needs project-specific page
selection:

```yaml
PLUGINS:
  - use: smgen-assistant
    options:
      config: .smgen-assistant.yaml
    pages:
      include: ['guides/**/*', 'index.md']
      exclude: ['guides/drafts/**/*']
    watch:
      - .smgen-assistant.yaml
```

Manifest watch paths resolve from the plugin directory. Activation watch paths
resolve from the project root.
<!-- </how> -->

## Write a Manifest

New plugins declare the `smgen/v1` API:

```yaml
apiVersion: smgen/v1
id: reading-time

pages:
  selectors:
    - include: ['**/*.md']
      exclude: ['legal/**/*']
      where:
        readingTime: true
  styles:
    - /reading-time/reading-time.css
  scripts:
    defer:
      - /reading-time/reading-time.js
  filters:
    - filters/reading-time.lua

assets:
  - from: static
    to: reading-time

hooks:
  prepare:
    run: [php, ./bin/prepare.php]
  finalize:
    run: [php, ./bin/finalize.php]

watch:
  - static
  - filters
```

The plugin ID may contain letters, numbers, periods, underscores, and hyphens.
SMGen rejects duplicate IDs, unsupported API versions, unknown fields, and
paths that escape the plugin or staged output.

## Select Pages

Each selector can set `include`, `exclude`, and `where`. Include and exclude
values use project-relative glob patterns. `**/*` includes root and nested
pages. A `where` mapping requires exact frontmatter values.

When a plugin omits `selectors`, its page resources apply to every page. The
`pages` mapping on a structured activation replaces the manifest selectors for
that project.

Page and directory frontmatter can make the final choice by plugin ID:

```yaml
---
plugins:
  reading-time: false
  smgen-assistant: true
---
```

Directory values inherit through `.fm.yaml`. Page values take precedence over
directory values and declarative selectors.

## Add Assets and Page Resources

`assets` copies files or directories from the plugin into staged output. SMGen
copies those assets only when at least one page selects the plugin. Project
files from `STATIC_DIR` copy afterward and can replace plugin assets. Plugin
asset sources cannot contain symbolic links.

Selected pages receive the plugin's styles, scripts, and Pandoc filters. Script
placement can be `head`, `defer`, or `body`. SMGen removes duplicate resource
URLs when a compatibility setting such as `BODY_SCRIPTS` names the same file.

## Run Build Hooks

Hooks receive an argument list, not a shell command string. An argument that
starts with `./` resolves from the plugin directory. SMGen sets
`SMGEN_PLUGIN_CONTEXT` to a JSON file with the plugin options, project paths,
base URL, and current phase. It also sets `SMGEN_PLUGIN_PHASE` to `prepare` or
`finalize`.

The context has this stable `smgen/v1` shape:

```json
{
  "apiVersion": "smgen/v1",
  "phase": "finalize",
  "plugin": {
    "id": "reading-time",
    "root": "/project/node_modules/reading-time",
    "manifest": "/project/node_modules/reading-time/smgen.plugin.yaml",
    "options": {}
  },
  "project": {"root": "/project", "environment": "production"},
  "paths": {
    "pages": "/project/pages",
    "output": "/project/docs",
    "stage": "/project/.docs.smgen-build.1234",
    "work": "/tmp/smgen.1234",
    "pageManifest": "/tmp/smgen.1234/pages.tsv",
    "generatedPages": null
  },
  "site": {"baseUrl": "/manual"}
}
```

The `prepare` hook runs before navigation and page discovery. Its
`paths.generatedPages` directory belongs to that plugin for the current build.
A file such as `paths.generatedPages/api/index.md` produces `api/index.html`.
`paths.stage` and `paths.pageManifest` are null during this phase. SMGen
removes the managed source directory after the build and clears marked files
left by an interrupted build. `.smgen-generated` is reserved for this use.

The `finalize` hook receives `paths.stage` after pages, search, feeds,
`llms.txt`, and the sitemap are ready. It can add files to staged output. A
failed hook stops the build before output replacement, so the previous site
remains available. `paths.generatedPages` is null during this phase. The page
manifest contains one tab-separated output and source path per rendered page.

Hooks run as trusted local programs with the build user's permissions. Review a
plugin before adding it to `PLUGINS`.

## Use the Bash Fallback

Set `SMGEN_ENGINE=bash` to use the compatibility builder. It continues to read
menu contributions from plugin manifests and runs project `before-smgen.sh`
and `after-smgen.sh` hooks. Versioned plugin assets, page selectors, generated
pages, and plugin hooks require the PHP engine.

The Bash fallback remains available through the current major release. It is
intended for migration and comparison while sites move build extensions into
plugins.

## Diagnose a Plugin

SMGen validates the manifest before it renders pages. Schema, duplicate ID,
path, and package-discovery errors therefore leave the prior output in place.
Hook output is written to the build terminal, followed by the failed argument
list and exit status.

`smgen watch` monitors only declared paths that exist when the watcher starts.
Restart it after creating a new watched directory. Run one comparison build
with `SMGEN_ENGINE=bash` when a migrated site needs to distinguish a plugin
problem from a PHP-engine compatibility problem.
