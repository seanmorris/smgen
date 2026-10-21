---
title: Linux
---

# Install on Linux

SMGen requires Bash 4 or newer, PHP CLI with the YAML extension, mikefarah
`yq` 4, and Pandoc. Install `curl` for initialization and updates. Install
`inotify-tools` for `smgen watch` and `aspell` for proofreading.

## Debian and Ubuntu

```bash
apt install bash php php-yaml pandoc curl inotify-tools aspell
```

## Fedora

```bash
dnf install bash php php-yaml pandoc curl inotify-tools aspell
```

## Arch Linux

```bash
pacman -Syu bash php php-yaml pandoc curl inotify-tools aspell
```

## Install `yq`

Package repositories may contain another program named `yq`. Check the version
and install the mikefarah release when needed:

```bash
YQ_VERSION=v4.47.2
wget -qO /usr/local/bin/yq "https://github.com/mikefarah/yq/releases/download/${YQ_VERSION}/yq_linux_amd64"
chmod +x /usr/local/bin/yq
yq --version
```

Use the corresponding binary name from the
[mikefarah/yq releases](https://github.com/mikefarah/yq/releases) on non-AMD64
systems.

## Verify the Build Tools

```bash
bash --version
php -v
php -m | grep -i '^yaml$'
yq --version
pandoc --version
```
