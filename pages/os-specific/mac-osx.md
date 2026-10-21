---
title: macOS
---
# macOS Specific Stuff

macOS uses `brew` for most installs; however, PHP may require some work (see below).

```bash
brew install bash pandoc yq
```

## Installing php-yaml on macOS

Installing PHP can be tricky on non-Linux systems. The default install provided by Homebrew does not include the yaml extension, so we can use [shivammathur's distribution](https://setup-php.com/) to install PHP with yaml support.

Add the repositories to brew:

```bash
brew tap shivammathur/php
brew tap shivammathur/extensions
```

Install and override PHP with the newly installed version:

```bash
brew install shivammathur/php/php@8.4
brew link --overwrite --force shivammathur/php/php@8.4
```

Ensure PHP is installed correctly and the correct version is being invoked:

```bash
php -v
```

```bash
brew install shivammathur/extensions/yaml@8.4
```

Ensure the yaml extension is installed and usable from PHP:

```bash
php -m | grep yaml
```

## `smgen watch` is unavailable

Unfortunately inotify-tools is not available on macOS, so the file watcher is currently unavailable.

You can, however, manually call `smgen serve` and `smgen build`.
