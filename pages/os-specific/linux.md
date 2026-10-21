---
title: Linux
---

# Linux Specific Stuff

## Debian

Installing the dependencies on Debian and derivatives is simple. All but `yq` are available from the default repositories:

```bash
apt install php php-yaml bash pandoc inotify-tools

YQ_VERSION=v4.47.2
wget -qO /usr/local/bin/yq https://github.com/mikefarah/yq/releases/download/${YQ_VERSION}/yq_linux_amd64
chmod +x /usr/local/bin/yq
```

## Fedora

Installing the dependencies on Fedora and related distributions is straightforward:

```bash
dnf install php php-yaml bash pandoc inotify-tools

YQ_VERSION=v4.47.2
wget -qO /usr/local/bin/yq https://github.com/mikefarah/yq/releases/download/${YQ_VERSION}/yq_linux_amd64
chmod +x /usr/local/bin/yq
```

## Arch Linux

On Arch Linux and derivatives, install everything from the official repositories:

```bash
pacman -Syu php php-yaml bash pandoc util-linux inotify-tools

YQ_VERSION=v4.47.2
wget -qO /usr/local/bin/yq https://github.com/mikefarah/yq/releases/download/${YQ_VERSION}/yq_linux_amd64
chmod +x /usr/local/bin/yq
```

## Other Distributions

Install the equivalent packages using your distribution's package manager and install `yq` manually:

```bash
# Replace <pkg-manager> with your distro's package manager (e.g., apt, yum, zypper, apk).
<pkg-manager> install php php-yaml bash pandoc inotify-tools

YQ_VERSION=v4.47.2
wget -qO /usr/local/bin/yq https://github.com/mikefarah/yq/releases/download/${YQ_VERSION}/yq_linux_amd64
chmod +x /usr/local/bin/yq
```
