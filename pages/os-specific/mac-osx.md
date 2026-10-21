---
title: macOS
---

# Install on macOS

Install Bash, Pandoc, and `yq` with Homebrew. macOS already provides `curl`:

```bash
brew install bash pandoc yq
```

SMGen uses Bash 4 features. A system-wide `smgen` installation invokes the
Homebrew Bash selected by the script's environment or symlink setup. Confirm
that the active Bash is version 4 or newer:

```bash
bash --version
```

## Install PHP and YAML

Homebrew's standard PHP formula does not include the YAML extension. Add
shivammathur's PHP and extension taps:

```bash
brew tap shivammathur/php
brew tap shivammathur/extensions
```

Install a matching PHP and YAML extension. This example uses PHP 8.4:

```bash
brew install shivammathur/php/php@8.4
brew link --overwrite --force shivammathur/php/php@8.4
brew install shivammathur/extensions/yaml@8.4
```

Verify both PHP and the extension:

```bash
php -v
php -m | grep -i '^yaml$'
```

## Build Without the Watcher

`smgen watch` depends on Linux `inotify-tools`. Use two terminals on macOS:

```bash
smgen serve
```

Run a build in the other terminal after source changes:

```bash
smgen build
```
