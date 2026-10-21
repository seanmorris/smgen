#!/usr/bin/env bash
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
	echo "This installer must be run under sudo." >&2
	exit 1
fi

REPO_URL="https://github.com/seanmorris/smgen.git"
INSTALL_DIR="/usr/share/smgen"
SYMLINK="/usr/local/bin/smgen"
INSTALL_PARENT=$(dirname "${INSTALL_DIR}")
INSTALL_NAME=$(basename "${INSTALL_DIR}")
STAGING_DIR=""
BACKUP_DIR=""

cleanup()
{
	[ -z "${STAGING_DIR}" ] || rm -rf -- "${STAGING_DIR}"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [ -e "${INSTALL_DIR}" ] && [ ! -d "${INSTALL_DIR}/.git" ]; then
	echo "Refusing to replace ${INSTALL_DIR}: it is not an SMGen Git checkout." >&2
	exit 1
fi

mkdir -p "${INSTALL_PARENT}"
STAGING_DIR=$(mktemp -d "${INSTALL_PARENT}/.${INSTALL_NAME}.install.XXXXXX")
echo "Downloading SMGen to ${STAGING_DIR}"
git clone --depth 1 "${REPO_URL}" "${STAGING_DIR}/checkout"
test -x "${STAGING_DIR}/checkout/smgen.sh"

if [ -e "${INSTALL_DIR}" ]; then
	BACKUP_DIR=$(mktemp -d "${INSTALL_PARENT}/.${INSTALL_NAME}.backup.XXXXXX")
	rmdir "${BACKUP_DIR}"
	mv "${INSTALL_DIR}" "${BACKUP_DIR}"
fi

if ! mv "${STAGING_DIR}/checkout" "${INSTALL_DIR}"; then
	[ -z "${BACKUP_DIR}" ] || mv "${BACKUP_DIR}" "${INSTALL_DIR}"
	exit 1
fi
rm -rf -- "${STAGING_DIR}"
STAGING_DIR=""
[ -z "${BACKUP_DIR}" ] || rm -rf -- "${BACKUP_DIR}"

echo "Linking ${INSTALL_DIR}/smgen.sh to ${SYMLINK}"
ln -sfn "${INSTALL_DIR}/smgen.sh" "${SYMLINK}"
chmod +x "${INSTALL_DIR}/smgen.sh"

echo "Installation complete. Run 'smgen' to build your site."
