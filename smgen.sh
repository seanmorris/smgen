#!/usr/bin/env bash

set -euo pipefail

DEFAULT_THEME=theme-cosmic

if [ -f .smgen.yaml ]; then
	source <(yq e . -os .smgen.yaml)
fi

if [ -f .smgen-rc ]; then
	# shellcheck source=/dev/null
	. .smgen-rc
fi

if [ -f .env ]; then
	# shellcheck source=/dev/null
	. .env
fi

OUTPUT_DIR=${OUTPUT_DIR:-"./docs"}
TEMPLATE_DIR=${TEMPLATE_DIR:-"./templates"}
STATIC_DIR=${STATIC_DIR:-"./static"}
PAGES_DIR=${PAGES_DIR:-"./pages"}

DEV_PORT=${DEV_PORT:-"8000"}

SCRIPT_DIR=$( cd -- "$( dirname -- "$( readlink -f "${BASH_SOURCE[0]}" )" )" &> /dev/null && pwd )

resolve_template_path()
{
	local candidate="$1"
	local template_root
	local resolved

	template_root="$(readlink -f "${TEMPLATE_DIR}")"
	resolved="$(readlink -f "${candidate}")" || return 1

	case "${resolved}" in
		"${template_root}"|"${template_root}/"*)
			printf '%s\n' "${resolved}"
			;;
		*)
			return 1
			;;
	esac
}

case "${1:-""}" in
	init|i)
		if [ -n "$(ls -A ./)" ]; then
			echo "Directory is not empty."
			exit 1
		fi;

		mkdir ${OUTPUT_DIR}
		mkdir pages/
		mkdir static/
		mkdir templates/

		cp "${SCRIPT_DIR}/templates/page.php" "${SCRIPT_DIR}/templates/header.php" "${SCRIPT_DIR}/templates/footer.php" templates/
		cp "${SCRIPT_DIR}/static/default.css" "${SCRIPT_DIR}/static/main.js" static/
		cp "${SCRIPT_DIR}/static/search.svg" "${SCRIPT_DIR}/static/github.svg" static/
		cp "${SCRIPT_DIR}/static/sun.svg" "${SCRIPT_DIR}/static/moon.svg" static/
		cp "${SCRIPT_DIR}/.smgen-rc-default" ./.smgen-rc

		PAGE_URL="https://jaspervdj.be/lorem-markdownum/markdown.txt?p=5"
		curl "${PAGE_URL}" -o ./pages/index.md
		;;

	build|b)
		if [ "$#" -eq 1 ] && [ -f before-smgen.sh ]; then
			before-smgen.sh
		fi

		PHP=${PHP:-"php"}
		PANDOC=${PANDOC:-"pandoc"}
		YQ=${YQ:-"yq"}
		UUID=${UUID:-"uuid"}
		SMG_SEARCH=${SMG_SEARCH:-"smgen-search"}

		BASE_URL=${BASE_URL:-""}
		PRODUCT_NAME=${PRODUCT_NAME:-""}
		ORGANIZATION=${ORGANIZATION:-""}
		TAGLINE=${TAGLINE:-""}

		HEADER=${HEADER:-"templates/header.php"}
		FOOTER=${FOOTER:-"templates/footer.php"}

		PHP_FLAGS='-d display_errors=stderr -d auto_prepend_file="'${SCRIPT_DIR}/helpers/bootstrap.php'" -d include_path="'${SCRIPT_DIR}/helpers'"'

		STYLES=${STYLES:-""}
		INLINE_STYLES=${INLINE_STYLES:-""}

		SCRIPTS=${SCRIPTS:-""}
		INLINE_SCRIPTS=${INLINE_SCRIPTS:-""}
		BODY_SCRIPTS=${BODY_SCRIPTS:-""}
		INLINE_BODY_SCRIPTS=${INLINE_BODY_SCRIPTS:-""}

		TITLE_PREFIX=${TITLE_PREFIX:-""}

		if [ "${TITLE_PREFIX}" != "" ]; then
			TITLE_PREFIX="--title-prefix=${TITLE_PREFIX}"
		fi

		HIGHLIGHT_STYLE=${HIGHLIGHT_STYLE:-"zenburn"};

		if [ "${HIGHLIGHT_STYLE}" != "" ]; then
			HIGHLIGHT_STYLE="--highlight-style ${HIGHLIGHT_STYLE}"
		fi

		if ! command -v "${PHP}" >/dev/null 2>&1; then
			echo "php is required."
			exit 1
		fi

		if ! command -v "${YQ}" >/dev/null 2>&1; then
			echo "yq is required."
			exit 1
		fi

		if ! command -v "${PANDOC}" >/dev/null 2>&1; then
			echo "pandoc is required."
			exit 1
		fi

		if ! command -v "${UUID}" >/dev/null 2>&1; then
			echo "uuid is required."
			exit 1
		fi

		echo -e "\e[33;4mBuilding pages...\e[0m"

		STYLE_ARGS=""
		INLINE_STYLE_ARGS=""

		if [[ -n "${STYLES}" ]]; then
			while IFS= read -r STYLE; do
				STYLE_ARGS+="--css ${STYLE} "
			done <<< "${STYLES}"
		fi

		if [[ -n "${INLINE_STYLES}" ]]; then
			while IFS= read -r INLINE_STYLE; do
				INLINE_STYLE_ARGS+="-H ${INLINE_STYLE} "
			done <<< "${INLINE_STYLES}"
		fi

		{

			if [ "$#" -gt 1 ]; then
				find "${2}" -type f -print0
			else
				find "${PAGES_DIR}" -type f -print0
			fi
		} | while IFS= read -r -d $'\0' PAGE_FILE; do {

			DIR=$( dirname "${PAGE_FILE}" );
			BASE=$( basename "${PAGE_FILE}" );
			EXT="${BASE##*.}"
			PAGE_NAME="${BASE%.*}"
			DEST="${DIR#"${PAGES_DIR}"}/${PAGE_NAME}.html"

			# Skip directory-level frontmatter
			if [ "${PAGE_NAME}.${EXT}" == ".fm.yaml" ]; then
				continue;
			fi

			echo -e "\e[37m  ${PAGE_FILE}...\e[0m"

			# Check if the file has frontmatter
			HAS_FM=
			FIRST_LINE=$(head -n 1 "${PAGE_FILE}")
			if [[ "${FIRST_LINE}" == "---" ]]; then
				HAS_FM=1
			fi

			# Make sure the matching subdirectory exists
			mkdir -p "${OUTPUT_DIR}/${DIR#"${PAGES_DIR}"}"

			# Determine the template
			TEMPLATE=
			if [ "${HAS_FM}" == "1" ]; then
				TEMPLATE=$( "${YQ}" --front-matter=extract '.template' "${PAGE_FILE}" )
			fi

			if [ "${TEMPLATE}" != "null" ] && [ "${TEMPLATE}" != "" ]; then
				if ! TEMPLATE="$(resolve_template_path "${TEMPLATE}")"; then
					echo "Template must be inside ${TEMPLATE_DIR}: ${TEMPLATE}" >&2
					exit 1
				fi
			fi

			# Template fallback logic
			if [ "${TEMPLATE}" == "null" ] || [ "${TEMPLATE}" == "" ]; then
				TEMPLATE=${TEMPLATE_DIR}/page.php

				# Check for a .php template for the current file's extension
				if [ -f "${TEMPLATE_DIR}/${EXT}-page.php" ]; then
					TEMPLATE=${TEMPLATE_DIR}/${EXT}-page.php
				fi

				# Check for a .html template for the current file's extension
				if [ -f "${TEMPLATE_DIR}/${EXT}-template.html" ]; then
					TEMPLATE=${TEMPLATE_DIR}/${EXT}-template.html
				fi
			fi

			# Table of contents
			TOC_FLAG=
			if [ "${HAS_FM}" == "1" ]; then
				TOC=$( "${YQ}" --front-matter=extract '.TOC' "${PAGE_FILE}" )
				if [ "${TOC}" == "null" ] || [ "${TOC}" == "" ] || [ "${TOC}" == "true" ]; then
					TOC_FLAG=--toc
				fi
			else
				TOC_FLAG=--toc
			fi

			TMP_FILE=/tmp/$( ${UUID} ).html

			# Build the final template
			BASE_URL=${BASE_URL}\
			PRODUCT_NAME=${PRODUCT_NAME}\
			ORGANIZATION=${ORGANIZATION}\
			TAGLINE=${TAGLINE}\
			HEADER=${HEADER}\
			FOOTER=${FOOTER}\
			DEFAULT_THEME=${DEFAULT_THEME}\
			PAGES_DIR=${PAGES_DIR}\
			SCRIPTS=${SCRIPTS}\
			INLINE_SCRIPTS=${INLINE_SCRIPTS}\
			BODY_SCRIPTS=${BODY_SCRIPTS}\
			INLINE_BODY_SCRIPTS=${INLINE_BODY_SCRIPTS}\
			CURRENT_PAGE=${DEST}\
			"${PHP}" ${PHP_FLAGS} "${TEMPLATE}" "${PAGE_FILE}" > "${TMP_FILE}"

			# Build the HTML
			BASE_URL=${BASE_URL}\
			"${PANDOC}" --data-dir=. -s -f markdown -t html \
				${HIGHLIGHT_STYLE} ${TOC_FLAG} ${TITLE_PREFIX} \
				--lua-filter="${SCRIPT_DIR}/helpers/domain.lua" \
				--template="${TMP_FILE}" \
				${INLINE_STYLE_ARGS} \
				${STYLE_ARGS} \
				-o "${OUTPUT_DIR}/${DEST}" \
				"${PAGE_FILE}"

			# Cleanup
			rm "${TMP_FILE}"

		}; done;

		if [ "$#" -eq 1 ]; then

			echo -e "\e[33;4mAssembing sitemap...\e[0m"
			echo -e "\e[37m  ${OUTPUT_DIR}/sitemap.xml...\e[0m"
			"${PHP}" ${PHP_FLAGS} "${SCRIPT_DIR}/helpers/sitemap.php" "${BASE_URL}" "${OUTPUT_DIR}" > "${OUTPUT_DIR}/sitemap.xml"

			if command -v "${SMG_SEARCH}" >/dev/null 2>&1; then
				echo -e "\e[33;4mAssembing search index...\e[0m"
				"${SMG_SEARCH}" build-index "${PAGES_DIR}" "${STATIC_DIR}/search.bin"
			fi

			if [ "$#" -eq 1 ] && [ -n "$(ls -A ./)" ]; then
				echo -e "\e[33;4mCopying static assets...\e[0m"
				cp -prfv "${STATIC_DIR}/"* "${OUTPUT_DIR}/";
			fi

			if [ -f after-smgen.sh ]; then
				after-smgen.sh
			fi
		fi

		;;

	watch|w)
		echo "ctrl+c to exit..."
		sleep 1

		EVENTS="create,modify,delete,move"

		trap 'kill $(jobs -p)' EXIT

		php -S "localhost:${DEV_PORT}" -t ${OUTPUT_DIR} &
		SERVER_PID=$!

		"${BASH_SOURCE[0]}" build

		inotifywait -m -r -e "$EVENTS" --format '%w%f %e' "${PAGES_DIR}" "${STATIC_DIR}" \
		| while read -r FILEPATH EVENT; do
			if [[ "${FILEPATH}" == "${PAGES_DIR}"* ]]; then
				if [[ ${EVENT} == *"CREATE"* || ${EVENT} == *"MOVED_TO"* || ${EVENT} == *"ISDIR"* ]]; then
					"${BASH_SOURCE[0]}" build
				else
					"${BASH_SOURCE[0]}" build "${FILEPATH}"
				fi
			fi

			if [[ "${FILEPATH}" == "${STATIC_DIR}"* ]]; then
				RELATIVE_PATH="${FILEPATH#"${STATIC_DIR}/"}"
				DEST_PATH="${OUTPUT_DIR}/${RELATIVE_PATH}"
				DEST_DIR="$(dirname "${DEST_PATH}")"

				if [[ ${EVENT} == *"DELETE"* || ${EVENT} == *"MOVED_FROM"* ]]; then
					rm -rfv "${DEST_PATH}"
					continue
				fi

				mkdir -p "${DEST_DIR}"
				cp -prfv "${FILEPATH}" "${DEST_PATH}"
			fi
		done;

		wait ${SERVER_PID}
		;;
	serve|s)
		echo "ctrl+c to exit..."
		sleep 1
		php -S "localhost:${DEV_PORT}" -t ${OUTPUT_DIR}
		;;

	proofread|pr)
		ASPELL=${ASPELL:-"aspell"}
		ASPELL_DICT=${ASPELL_DICT:-"aspell.txt"}
		ASPELL_FLAGS='-t --home-dir=./ --personal='"${ASPELL_DICT}"
		if [ "$#" -gt 1 ]; then
			cat "${2}" | ${ASPELL} ${ASPELL_FLAGS} list | sort | uniq
		else
			CURRENT_CODE=0
			while IFS= read -r -d $'\0' FILE; do
				if ${ASPELL} ${ASPELL_FLAGS} list < "${FILE}" | grep -q .; then
					CURRENT_CODE=1
					echo "${FILE} needs proofreading."
				fi
			done < <( find ${PAGES_DIR} -type f -name "*.md" -print0 )
			exit ${CURRENT_CODE}
		fi
		;;
	create-random-page)
		PAGE_URL="https://jaspervdj.be/lorem-markdownum/markdown.txt?p=5"
		PAGE_FILE="pages/$(shuf -n 1 /usr/share/dict/words).md"
		if [ -e "${PAGE_FILE}" ]; then
			echo "File exists: ${PAGE_FILE}"
			exit 1
		fi
		curl "${PAGE_URL}" -o "${PAGE_FILE}"
		;;

	help|-h|--help|"")
		echo "Usage: $(basename "$0") <command>"
		echo
		echo "Commands:"
		echo "  init                Initialize a new site in current directory"
		echo "  build               Build the site from Markdown to HTML"
		echo "  serve               Serve the site locally (requires PHP)"
		echo "  proofread           Proofread one or all documents"
		echo "  create-random-page  Create a random lorem markdown page"
		echo "  help                Show this help message"
		exit 0
		;;

	*)
		echo "Unknown command: $1"
		echo "Run '$(basename "$0") help' for usage."
		exit 1
		;;

esac
