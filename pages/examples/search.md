---
title: Search Integration Example
---

# Search Integration Example

This example demonstrates how to add full-text search to your site using [smgen-search](https://www.npmjs.com/package/smgen-search).

## Prerequisites

- [Node.js](https://nodejs.org/) (v14 or later)
- [yq](https://github.com/mikefarah/yq)
- [smgen-search CLI](https://www.npmjs.com/package/smgen-search)

## 1. Install `smgen-search`

Install the pinned search tooling globally:

```bash
npm install -g smgen-search@0.0.1
```

## 2. Configure the search index build

Enable search in `.smgen.yaml`:

```yaml
SEARCH_ENABLED: 1
SEARCH_READER: /vendor/smgen-search/SearchReader.mjs
DEFER_SCRIPTS:
  - /search.js
```

The `smgen build` command generates `search.bin` directly in the staged output
when the `smgen-search` CLI is available. With `SEARCH_ENABLED=1`, the build
fails if neither the CLI nor a prebuilt `static/search.bin` is available. Use
`SEARCH_ENABLED=auto` to enable search only when one of those inputs exists.

If you installed `smgen-search` locally (for example, in `node_modules`), you can
override the search command by setting the `SMG_SEARCH` variable in your
`.smgen-rc` or `.env` file:

```bash
# .smgen-rc or .env
SMG_SEARCH=./node_modules/.bin/smgen-search
```

## 3. Enable the search UI

The default templates render the search input only when search is enabled. The
reader is vendored under `static/vendor/smgen-search`, so the published site has
no runtime CDN dependency. `static/search.js` waits until the input is focused
or receives a query before loading both the reader module and binary index.

The generated input contains the local module and index locations:

```php
<input id="search-query"
	   type="search"
	   data-search-index="<?=resolveAssetUrl('/search.bin')?>"
	   data-search-reader="<?=resolveAssetUrl(getConf('SEARCH_READER'))?>"
	   data-search-results="#search-results" />
<ul class="search-menu" id="search-results"></ul>
```

## 4. Build and test

Run the standard build command:

```bash
smgen build
```

Open your site in a browser and try typing in the search box in the header. Results will appear as you type.

The reader source is distributed under Apache-2.0 with its attribution in
`static/vendor/smgen-search/NOTICE`.
