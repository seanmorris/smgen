---
title: Deployment
weight: 4
---

# Deployment

## Custom Hosting

You can host the generated site by uploading the contents of `docs/` to any static-hosting provider (Netlify, S3, etc.).

## GitHub Pages

Make sure to set `BASE_URL` correctly. This is especially important if the site exists in a subdirectory on your domain. Use the full public URL, without a trailing slash. You can either set it in `.smgen-rc` or use an environment variable on the CLI:

```bash
BASE_URL=https://example.com/subdir smgen build
```

GitHub Pages can be handled in two ways: you can simply build to the `docs/` directory and commit that, or you can use a CI workflow that builds with the correct `BASE_URL` automatically.

### Serving from docs/

Enable GitHub Pages deployment from your branch in repository settings

![Select "Deploy from Branch" in the dropdown](/github-pages-branch.png)

Select your branch, and the `/docs` folder. Make sure to use the `/docs` directory or the entire repository will be served.

![Make sure to pay attention to the folder selector](/github-pages-select-branch.png)

### CI Integration

You can also build the site completely within a workflow and publish the resulting artifact. This has the advantage of keeping the repository cleaner, and you can also auto-detect your GitHub Pages `BASE_URL`, so there's no need to manage it manually.

Although it's slightly more complicated, this is the recommended way to publish to GitHub Pages.

You'll need to configure GitHub Pages to accept deployments from Actions.

**This is not needed if you intend to commit your `/docs` directory and serve that via GitHub Pages.**

![Select "GitHub Actions" in the dropdown](/github-pages-actions.png)

This repository's `.github/workflows/build-and-deploy.yml` is a complete example. It:

- validates pull requests and pushes on both Ubuntu and macOS;
- pins third-party actions and the search indexer version;
- grants deployment permissions only to the deployment job;
- deploys only successful pushes to `master`; and
- gets the public `base_url` from `actions/configure-pages` before building.

The essential build step is:

```yaml
- name: Configure Pages
  id: pages
  uses: actions/configure-pages@v5

- name: Build site
  env:
    BASE_URL: ${{ steps.pages.outputs.base_url }}
  run: ./smgen.sh build
```

There is no output-clearing step. SMGen stages and replaces the complete output directory itself, and preserves the previous output when generation fails.
