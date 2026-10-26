---
title: Navigation and Plugins
weight: 4
llms:
  section: guides
  description: Define static menus, regions, plugins, and publication timelines.
---

# Navigation and Plugins

SMGen compiles navigation before it renders any page. Pages, directory metadata,
site configuration, and ordered plugins contribute links to named menus.
Templates place those menus in named regions.

The generated hierarchy and markup are static. A visitor does not need
JavaScript to load or construct navigation.

## Define Menus and Regions

<!-- <how keywords="second sidebar menu, navigation block, define tree menu, place menu in sidebar"> -->
To add another sidebar menu, define it under `MENUS`, then list its name under
`MENU_REGIONS.sidebar`. Choose the built-in `flat`, `tree`, or `timeline`
renderer that matches the navigation you want.

Put structured menu configuration in `.smgen.yaml` or an environment-specific
YAML file:

```yaml
MENUS:
  primary:
    label: Primary
    renderer: flat
    links:
      - id: project
        href: https://example.com/project
        title: Project
        variant: cta
  documentation:
    label: Documentation
    renderer: tree
    hierarchy: source
  archive:
    label: Archive
    renderer: timeline
MENU_REGIONS:
  header: [primary]
  sidebar: [documentation, archive]
```

Each menu supports:

- `label`: visible heading text when `showLabel` is true.
- `renderer`: `flat`, `tree`, or `timeline`. Default: `flat`.
- `hierarchy`: `manual` or `source`. Default: `manual`.
- `showLabel`: render the menu label. Default: `false`.
- `links`: explicit page, external, or group links.
- `selectors`: rules that select pages by path and frontmatter.

The `source` hierarchy creates groups from directories under `PAGES_DIR`.
Directory metadata from `.fm.yaml` controls the group title, weight, expansion,
and menu-specific settings. The `manual` hierarchy uses each link's `parent`
value.

A region contains an ordered list of menu names. A menu may appear in more than
one region. Every referenced menu must exist.
<!-- </how> -->

## Add Explicit Links

A link must define exactly one target:

- `page`: a source-relative page path such as `guides/install.md`.
- `href`: an internal or external URL.
- `group: true`: a non-page parent for other links.

External and group links require an `id`. Page links receive a stable ID based
on their source path, but an explicit link may replace it. Link IDs may contain
letters, numbers, periods, underscores, colons, slashes, and hyphens.

```yaml
MENUS:
  primary:
    renderer: flat
    links:
      - id: home
        page: index.md
        title: Home
        weight: -100
      - id: resources
        group: true
        title: Resources
        expanded: true
      - id: source
        href: https://github.com/example/project
        title: Source
        parent: resources
        icon: github
        variant: icon-only
        description: Browse the repository
```

Links support these settings:

- `title`: displayed link or group text.
- `parent`: ID of another link in the same menu.
- `weight`: numeric sort weight. Lower values appear first.
- `expanded`: initial state for a group rendered with `details`.
- `variant`: `default`, `cta`, or `icon-only`.
- `icon`: token used in the `menu-link-icon--<icon>` CSS class.
- `description`: text rendered in a `small` element inside the link.
- `replace`: allow a declarative link to replace another provider's link with the same ID.

SMGen sorts siblings by weight and then by ID. It rejects duplicate IDs,
unknown parents, and parent cycles before replacing the current output.

## Select Menus from Pages

<!-- <how keywords="menu membership, page opt out, veto selector, public guides, menu exceptions"> -->
Use `menus` in page frontmatter to join one or more named menus:

```yaml
---
title: Release Notes
menus:
  primary:
    title: Releases
    weight: 20
  documentation:
    parent: group:guides
    expanded: true
  archive: true
---
```

A list provides membership without per-menu settings:

```yaml
menus: [documentation, archive]
```

Set a membership to `false` to remove that page from the menu. This also vetoes
a link supplied by a plugin selector:

```yaml
menus:
  documentation: false
```

When a page contains `menus`, SMGen does not infer legacy sidebar or archive
membership for that page. Pages without `menus` continue to use
`leftBarLink`, `date`, and `archive` as described under
[Legacy Navigation](#legacy-navigation).

Directory `.fm.yaml` files accept the same `menus` list or mapping. A directory
joins a flat or tree menu as a group. Timeline menus accept pages only. The
normal `title`, `weight`, and `open` values remain the defaults for that group.
<!-- </how> -->

## Render Menus in Templates

The core navigation helper exposes four template functions:

```php
<?php if (menuRegionExists('header')): ?>
  <nav><?php renderMenuRegion('header'); ?></nav>
<?php endif; ?>

<aside><?php renderMenu('documentation'); ?></aside>
```

- `renderMenu($name, $options = [])`: render one named menu.
- `renderMenuRegion($name, $options = [])`: render each menu assigned to a region.
- `menuRegionExists($name)`: report whether a region is defined.
- `renderNavBar()`: render the sidebar region for older templates.

The supported render options are `idPath`, which seeds stable group identifiers,
and `showLabel`, which overrides a flat or tree menu's configured label
visibility.

`renderMenu()` and `renderMenuRegion()` throw an exception for an unknown name.
Check optional regions with `menuRegionExists()` before rendering them.

## Timeline Menus

<!-- <how keywords="blogger archive, timeline calendar, timeline javascript, timeline css, archive stylesheet, every page"> -->
<!-- <what keywords="timeline behavior, calendar, expandable years and months, conditional assets"> -->
<!-- <when keywords="timeline date, date fallback, creation time"> -->
A timeline menu accepts content pages only. It renders:

- a linked calendar for the active or newest month
- expandable year and month groups
- counts for years, months, and days with more than one entry
- active page and date markers

SMGen chooses a selected page's date in this order:

1. author-supplied `date`
2. author-supplied `created`
3. filesystem birth time when the platform exposes it
4. the page's first Git addition time
5. filesystem modification time

If both author fields are absent, SMGen writes the selected fallback to the
source page as a full ISO 8601 `created` value. It writes the value once, after
menu validation succeeds. Later builds reuse it. Git does not store filesystem
creation or modification times, so the Git fallback uses commit history rather
than file metadata.

`modified` is optional and never generated. It overrides the source file
modification time used for that page's sitemap `lastmod` value.

Timeline markup uses native `details` elements. SMGen adds no timeline-specific
JavaScript. It copies and links `archive.css` only when a populated timeline is
assigned to a region. A timeline that is empty or never placed in a region adds
no stylesheet request.
<!-- </when> -->
<!-- </what> -->
<!-- </how> -->

## Menu Plugins

<!-- <how keywords="bulk page selection, public guides, include exclude where, plugin selectors"> -->
List plugin manifests in the order they should run:

```yaml
PLUGINS:
  - plugins/guides/smgen.plugin.yaml
  - plugins/releases/smgen.plugin.yaml
```

A plugin manifest contains an ID, menu contributions, and an optional alter
hook:

```yaml
id: guides
menus:
  documentation:
    links:
      - id: guides.root
        group: true
        title: Guides
        weight: -10
    selectors:
      - include: ["guides/**/*.md"]
        exclude: ["guides/drafts/**"]
        where:
          audience: public
        defaults:
          parent: guides.root
alter: menu-alter.php
```

Manifest paths resolve from the project root. An `alter` path resolves from the
manifest's directory.

A selector supports:

- `include`: source-relative glob patterns. Default: `**/*`.
- `exclude`: source-relative glob patterns removed from the match.
- `where`: exact frontmatter key and value matches.
- `defaults`: link settings applied to every selected page.

`*` matches within one path segment. `**` crosses directory boundaries. `?`
matches one character other than a slash. Frontmatter comparisons preserve
YAML types, so the boolean `true` does not equal the string `"true"`.

Site-level `MENUS` may also contain `links` and `selectors`. SMGen loads built-in
menu definitions first, then plugin manifests in `PLUGINS` order, then site
configuration. Explicit page and directory memberships apply after those
contributions.
<!-- </how> -->

## Alter Hooks

A plugin alter file must return a callable. SMGen invokes plugin callbacks in
manifest order, then invokes `helpers/menu-alter.php` when that site file
exists.

```php
<?php
return static function(
    array &$registry,
    array $catalog,
    array $context
): void {
    $registry['links']['primary']['project']['title'] = 'Project Source';
};
```

The callback receives:

- `$registry`: mutable `menus`, `links`, and `regions` arrays.
- `$catalog`: scanned `pages` and `directories` with their metadata.
- `$context`: the provider name and absolute project directory.

An alter callback runs before hierarchy validation and rendering. Invalid menu
names, link fields, renderers, regions, parent references, or timeline entries
fail the build and preserve the last successful output.

## Legacy Navigation

<!-- <what keywords="legacy inference, menus field, leftbarlink behavior, gradual migration"> -->
<!-- <how keywords="migrate old sidebar, keep rendernavbar, named menu migration"> -->
SMGen always defines the built-in `sidebar` tree menu and `archive` timeline
menu. If `MENU_REGIONS.sidebar` is absent, `NAVIGATION_WIDGETS` maps `tree` to
`sidebar` and `archive` to `archive`.

Pages without a `menus` field use the original rules:

- `leftBarLink: false` removes the page from the sidebar tree.
- `leftBarShow: false` hides the sidebar on that page.
- a page with `date` joins the archive unless `archive: false` is set.
- `ARCHIVE_TITLE` supplies the archive heading.

Pages with a `menus` field use explicit named menu membership instead of these
legacy rules.

Existing project helpers that define their own `renderNavBar()` remain usable.
The core named-menu functions load alongside that override, so a project can
migrate templates and menus incrementally.
<!-- </how> -->
<!-- </what> -->
