#!/usr/bin/env bash
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
	echo "This installer must be run under sudo."
	exit 1
fi

REPO_URL="https://github.com/seanmorris/smgen.git"
INSTALL_DIR="/usr/share/smgen"
SYMLINK="/usr/local/bin/smgen"

if [ -d "${INSTALL_DIR}/.git" ]; then
	echo "Updating existing installation in ${INSTALL_DIR}"
	git -C "${INSTALL_DIR}" pull --ff-only
else
	echo "Cloning repository to ${INSTALL_DIR}"
	rm -rf "${INSTALL_DIR}"
	git clone "${REPO_URL}" "${INSTALL_DIR}"
fi

echo "Linking ${INSTALL_DIR}/smgen.sh to ${SYMLINK}"
ln -sf "${INSTALL_DIR}/smgen.sh" "${SYMLINK}"

chmod +x "${INSTALL_DIR}/smgen.sh" "${SYMLINK}"

echo "Installation complete. Run 'smgen' to build your site."
