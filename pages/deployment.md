---
title: Deployment
weight: 6
---

# Deployment

SMGen produces static files under `OUTPUT_DIR`, which defaults to `docs/`. A
web server does not need PHP, Pandoc, `yq`, or SMGen after the build finishes.

## Set the Public URL

<!-- <how keywords="base_url, public root, subdirectory, trailing slash, generated links"> -->
<!-- <where keywords="public url, site root, production url"> -->
Set `BASE_URL` to the site's public root before a production build:

```bash
BASE_URL=https://example.com/project smgen build
```

SMGen uses this value for stylesheet, script, navigation, search, and sitemap
URLs. It accepts the value with or without a trailing slash.
<!-- </where> -->
<!-- </how> -->

## Static Hosting

Build the site, then publish the contents of `OUTPUT_DIR` to the static host:

```bash
smgen build
```

Upload the directory as one release. SMGen already removes stale files by
building a complete replacement output.

## GitHub Pages from a Branch

To publish committed output, configure GitHub Pages to deploy from the desired
branch and its `/docs` directory.

![Select Deploy from a branch](/github-pages-branch.png)

![Select the docs directory](/github-pages-select-branch.png)

Build with the repository's final Pages URL before committing the output. This
method requires generated files to remain under version control.

## GitHub Pages with Actions

<!-- <how keywords="github build and publish, do not commit generated files, actions deployment, upload docs artifact"> -->
An Actions deployment builds the site on each push and uploads the generated
artifact. Configure Pages to use GitHub Actions:

![Select GitHub Actions](/github-pages-actions.png)

This repository's `.github/workflows/build-and-deploy.yml` provides a complete
example. It validates pushes and pull requests on Ubuntu and macOS, then deploys
successful pushes to `master`.

The deployment job obtains the public URL from GitHub before building:

```yaml
- name: Configure Pages
  id: pages
  uses: actions/configure-pages@v5

- name: Build site
  env:
    BASE_URL: ${{ steps.pages.outputs.base_url }}
  run: ./smgen.sh build
```

The workflow pins third-party actions and the search indexer, grants deployment
permissions only to the deployment job, and uploads `docs/` as the Pages
artifact. SMGen handles output replacement, so the workflow does not need a
separate cleanup step.
<!-- </how> -->
