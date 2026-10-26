# npm Package Release Outline

I. Package Set
   A. Publish `@seanmorris/smgen` as the main static site generator.
      1. Expose `smgen.sh` through the `smgen` executable.
      2. Include the helpers, templates, default configuration, and static assets required at runtime.
      3. Keep PHP, Pandoc, `yq`, and optional shell tools as documented system requirements.
   B. Publish `smgen-assistant` as the browser assistant builder.
      1. Expose the existing `smgen-assistant` executable.
      2. Include compiled browser and command-line assets.
      3. Exclude development-only evaluation runners from the runtime archive.
   C. Update `smgen-search` as the optional search indexer.
      1. Expose the existing `smgen-search` executable.
      2. Export the browser reader and Bloom filter modules for package consumers.
      3. Preserve the zero-dependency runtime.

II. Package Metadata
   A. Give each package a description, license, repository, homepage, issue tracker, keywords, and Node.js requirement.
   B. Limit each archive with an explicit `files` list.
   C. Declare public publication settings.
   D. Use semantic versions that have not appeared in the npm registry.

III. Runtime Corrections
   A. Make an installed `smgen` resolve core files from its npm package directory.
   B. Keep local and global executable links working through symbolic-link resolution.
   C. Correct `smgen-search` command parsing, environment settings, Markdown selection, frontmatter handling, and binary buffer boundaries.
   D. Return nonzero status codes for invalid commands and inputs.

IV. Documentation
   A. Make npm the primary installation method for each package.
   B. Retain the SMGen shell installer as an alternative for machines that do not use npm.
   C. Document system requirements separately from npm dependencies.
   D. Use only commands that work against the packed archives.

V. Verification
   A. Run each repository's regression suite.
   B. Inspect each archive with `npm pack --dry-run`.
   C. Install each archive into a clean temporary project.
   D. Run every packaged executable from `node_modules/.bin`.
   E. Build an SMGen fixture with the packed `smgen` and `smgen-search` packages.
   F. Build an assistant fixture with the packed `smgen-assistant` package.

VI. Publication
   A. Validate each release before publication.
      1. Accept stable tags in the `v0.0.0` form.
      2. Require the tag to match the package version exactly.
      3. Install locked dependencies without lifecycle scripts.
      4. Run the complete package checks and inspect the archive.
   B. Publish from GitHub Actions.
      1. Use a GitHub-hosted runner and a pinned npm CLI with OIDC support.
      2. Grant only repository read access and permission to request an identity token.
      3. Publish public packages with provenance after validation succeeds.
      4. Keep npm credentials out of repository secrets.
   C. Configure npm after the first publication.
      1. Publish the first registry version interactively with two-factor authentication.
      2. Register `publish.yml` as the trusted publisher for each package.
      3. Publish future versions by pushing one matching version tag.
   D. Verify registry metadata, archive integrity, executable installation, provenance, and public package pages.
   E. Replace temporary release-archive dependencies with registry versions after their first publication.

VII. Documentation Site
   A. Resume the SMGen-built `smgen-assistant` site after package verification.
   B. Replace release-archive installation examples with registry commands.
   C. Install pinned npm releases in the Pages workflow.
   D. Deploy the generated site through GitHub Pages.
