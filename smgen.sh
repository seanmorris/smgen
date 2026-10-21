#!/usr/bin/env bash

set -euo pipefail

SOURCE_PATH=${BASH_SOURCE[0]}
while [ -h "${SOURCE_PATH}" ]; do
	SOURCE_DIR=$( cd -P -- "$( dirname -- "${SOURCE_PATH}" )" >/dev/null 2>&1 && pwd )
	SOURCE_PATH=$(readlink "${SOURCE_PATH}")
	[[ ${SOURCE_PATH} != /* ]] && SOURCE_PATH="${SOURCE_DIR}/${SOURCE_PATH}"
done
SCRIPT_DIR=$( cd -P -- "$( dirname -- "${SOURCE_PATH}" )" >/dev/null 2>&1 && pwd )
SMGEN_CORE_STATIC_DIR="${SCRIPT_DIR}/static"
PROJECT_DIR=$(pwd -P)

SCALAR_CONFIG_KEYS=(
	BASE_URL OUTPUT_DIR PAGES_DIR TEMPLATE_DIR STATIC_DIR HELPERS_DIR
	DEFAULT_THEME PRODUCT_NAME ORGANIZATION TAGLINE TITLE_PREFIX HIGHLIGHT_STYLE
	HEADER FOOTER DEV_PORT INHERIT_CORE_STATIC SEARCH_ENABLED SEARCH_READER
	PHP PANDOC YQ SMG_SEARCH ASPELL ASPELL_DICT SMGEN_ENVIRONMENT environment
)
LIST_CONFIG_KEYS=(
	STYLES INLINE_STYLES SCRIPTS DEFER_SCRIPTS INLINE_SCRIPTS
	BODY_SCRIPTS INLINE_BODY_SCRIPTS CORE_ASSETS
)
CONFIG_KEYS=("${SCALAR_CONFIG_KEYS[@]}" "${LIST_CONFIG_KEYS[@]}")

declare -A CALLER_CONFIG_SET=()
declare -A CALLER_CONFIG_VALUE=()
for CONFIG_KEY in "${CONFIG_KEYS[@]}"; do
	if declare -p "${CONFIG_KEY}" >/dev/null 2>&1; then
		CALLER_CONFIG_SET["${CONFIG_KEY}"]=1
		CALLER_CONFIG_VALUE["${CONFIG_KEY}"]=${!CONFIG_KEY}
	fi
done

# Values assigned here are exported dynamically for PHP templates.
# shellcheck disable=SC2034
set_config_defaults()
{
	BASE_URL=""
	OUTPUT_DIR="./docs"
	PAGES_DIR="./pages"
	TEMPLATE_DIR="./templates"
	STATIC_DIR="./static"
	HELPERS_DIR="./helpers"
	DEFAULT_THEME="theme-default"
	PRODUCT_NAME=""
	ORGANIZATION=""
	TAGLINE=""
	TITLE_PREFIX=""
	HIGHLIGHT_STYLE="zenburn"
	HEADER="templates/header.php"
	FOOTER="templates/footer.php"
	DEV_PORT="8000"
	INHERIT_CORE_STATIC="0"
	SEARCH_ENABLED="auto"
	SEARCH_READER="/vendor/smgen-search/SearchReader.mjs"
	PHP="php"
	PANDOC="pandoc"
	YQ="yq"
	SMG_SEARCH="smgen-search"
	ASPELL="aspell"
	ASPELL_DICT="aspell.txt"
	SMGEN_ENVIRONMENT=""
	environment=""
	STYLES=""
	INLINE_STYLES=""
	SCRIPTS=""
	DEFER_SCRIPTS=""
	INLINE_SCRIPTS=""
	BODY_SCRIPTS=""
	INLINE_BODY_SCRIPTS=""
	CORE_ASSETS=""
}

yaml_has_key()
{
	local file=$1
	local key=$2
	local yaml_command=$3
	[ "$("${yaml_command}" e "has(\"${key}\")" "${file}")" = "true" ]
}

load_yaml_config()
{
	local file=$1
	local key value item value_type yaml_command

	[ -f "${file}" ] || return 0
	yaml_command=${YQ}

	for key in "${SCALAR_CONFIG_KEYS[@]}"; do
		if yaml_has_key "${file}" "${key}" "${yaml_command}"; then
			value=$("${yaml_command}" e -r ".${key}" "${file}")
			printf -v "${key}" '%s' "${value}"
		fi
	done

	for key in "${LIST_CONFIG_KEYS[@]}"; do
		if ! yaml_has_key "${file}" "${key}" "${yaml_command}"; then
			continue
		fi

		value=""
		value_type=$("${yaml_command}" e -r ".${key} | type" "${file}")
		if [ "${value_type}" = "!!seq" ]; then
			while IFS= read -r item; do
				if [ -n "${value}" ]; then
					value+=$'\n'
				fi
				value+="${item}"
			done < <("${yaml_command}" e -r ".${key}[]" "${file}")
		elif [ "${value_type}" != "!!null" ]; then
			value=$("${yaml_command}" e -r ".${key}" "${file}")
		fi
		printf -v "${key}" '%s' "${value}"
	done
}

load_config()
{
	set_config_defaults
	if [ "${CALLER_CONFIG_SET[YQ]:-}" = "1" ]; then
		YQ=${CALLER_CONFIG_VALUE[YQ]}
	fi

	if [ -f .smgen.yaml ]; then
		if ! command -v "${YQ}" >/dev/null 2>&1; then
			echo "yq is required to read .smgen.yaml." >&2
			exit 1
		fi
		load_yaml_config .smgen.yaml
	fi

	local selected_environment
	if [ "${CALLER_CONFIG_SET[SMGEN_ENVIRONMENT]:-}" = "1" ]; then
		selected_environment=${CALLER_CONFIG_VALUE[SMGEN_ENVIRONMENT]}
	elif [ "${CALLER_CONFIG_SET[environment]:-}" = "1" ]; then
		selected_environment=${CALLER_CONFIG_VALUE[environment]}
	else
		selected_environment=${SMGEN_ENVIRONMENT:-${environment:-}}
	fi
	if [ -n "${selected_environment}" ]; then
		if [[ ! ${selected_environment} =~ ^[A-Za-z0-9._-]+$ ]]; then
			echo "Invalid SMGEN_ENVIRONMENT: ${selected_environment}" >&2
			exit 1
		fi
		load_yaml_config ".smgen.${selected_environment}.yaml"
	fi

	if [ -f .smgen-rc ]; then
		# shellcheck source=/dev/null
		. ./.smgen-rc
	fi

	if [ -f .env ]; then
		# shellcheck source=/dev/null
		. ./.env
	fi

	for CONFIG_KEY in "${CONFIG_KEYS[@]}"; do
		if [ "${CALLER_CONFIG_SET[${CONFIG_KEY}]:-}" = "1" ]; then
			printf -v "${CONFIG_KEY}" '%s' "${CALLER_CONFIG_VALUE[${CONFIG_KEY}]}"
		fi
		export "${CONFIG_KEY}=${!CONFIG_KEY}"
	done

	SMGEN_ENVIRONMENT=${SMGEN_ENVIRONMENT:-${environment:-}}
	export SMGEN_ENVIRONMENT
}

require_command()
{
	local command_name=$1
	local description=${2:-${command_name}}
	if ! command -v "${command_name}" >/dev/null 2>&1; then
		echo "${description} is required." >&2
		exit 1
	fi
}

canonical_existing_path()
{
	# The PHP source is intentionally single-quoted shell text.
	# shellcheck disable=SC2016
	"${PHP}" -r '
		$path = realpath($argv[1]);
		if($path === false) exit(1);
		echo $path;
	' "${1}"
}

resolve_project_helper_path()
{
	local helper_name=$1
	local project_candidate="${HELPERS_DIR}/${helper_name}"

	if [ -f "${project_candidate}" ]; then
		printf '%s\n' "${project_candidate}"
		return 0
	fi

	printf '%s\n' "${SCRIPT_DIR}/helpers/${helper_name}"
}

resolve_asset_url()
{
	local path=$1
	local base=${BASE_URL%/}
	case "${path}" in
		http://*|https://*|//*|data:*|mailto:*|tel:*) printf '%s\n' "${path}" ;;
		*)
			if [ -n "${base}" ]; then
				printf '%s/%s\n' "${base}" "${path#/}"
			else
				printf '/%s\n' "${path#/}"
			fi
			;;
	esac
}

resolve_template_path()
{
	local candidate=$1
	local template_root resolved

	template_root=$(canonical_existing_path "${TEMPLATE_DIR}") || return 1
	resolved=$(canonical_existing_path "${candidate}") || return 1

	case "${resolved}" in
		"${template_root}"|"${template_root}/"*) printf '%s\n' "${resolved}" ;;
		*) return 1 ;;
	esac
}

copy_static_tree()
{
	local source_dir=$1
	local destination_dir=$2
	local entries=()

	[ -d "${source_dir}" ] || return 0
	shopt -s dotglob nullglob
	entries=("${source_dir}"/*)
	shopt -u dotglob nullglob
	[ "${#entries[@]}" -gt 0 ] || return 0
	cp -Rp "${entries[@]}" "${destination_dir}/"
}

copy_selected_static_assets()
{
	local source_dir=$1
	local destination_dir=$2
	local assets=$3
	local asset relative source_path resolved_source source_root dest_path

	[ -n "${assets}" ] || return 0
	source_root=$(canonical_existing_path "${source_dir}")

	while IFS= read -r asset; do
		[ -n "${asset}" ] || continue
		relative=${asset#/}
		if [ -z "${relative}" ] || [[ /${relative}/ == *"/../"* ]]; then
			echo "Invalid core asset path: ${asset}" >&2
			return 1
		fi

		source_path="${source_dir}/${relative}"
		resolved_source=$(canonical_existing_path "${source_path}") || {
			echo "Core asset not found: ${asset}" >&2
			return 1
		}
		case "${resolved_source}" in
			"${source_root}/"*) ;;
			*) echo "Core asset must stay inside ${source_dir}: ${asset}" >&2; return 1 ;;
		esac

		dest_path="${destination_dir}/${relative}"
		mkdir -p "$(dirname "${dest_path}")"
		cp -Rp "${resolved_source}" "${dest_path}"
	done <<< "${assets}"
}

frontmatter_value()
{
	local file=$1
	local expression=$2
	local first_line

	IFS= read -r first_line < "${file}" || true
	first_line=${first_line%$'\r'}
	if [ "${first_line}" != "---" ]; then
		printf '%s\n' ""
		return 0
	fi

	"${YQ}" --front-matter=extract -r "${expression}" "${file}"
}

validate_page_argument()
{
	local requested=$1
	local pages_root requested_path

	[ -e "${requested}" ] || {
		echo "Page path does not exist: ${requested}" >&2
		return 1
	}
	pages_root=$(canonical_existing_path "${PAGES_DIR}")
	requested_path=$(canonical_existing_path "${requested}")
	case "${requested_path}" in
		"${pages_root}"|"${pages_root}/"*) ;;
		*) echo "Page path must stay inside ${PAGES_DIR}: ${requested}" >&2; return 1 ;;
	esac
}

validate_output_path()
{
	local output_parent output_name project_root protected protected_path

	if [ -L "${OUTPUT_DIR}" ]; then
		echo "OUTPUT_DIR must not be a symbolic link: ${OUTPUT_DIR}" >&2
		return 1
	fi

	output_parent=$(dirname "${OUTPUT_DIR}")
	output_name=$(basename "${OUTPUT_DIR}")
	if [ -z "${output_name}" ] || [ "${output_name}" = "." ] || [ "${output_name}" = ".." ] || [ "${output_name}" = "/" ]; then
		echo "Unsafe OUTPUT_DIR: ${OUTPUT_DIR}" >&2
		return 1
	fi
	mkdir -p "${output_parent}"
	output_parent=$(canonical_existing_path "${output_parent}")
	OUTPUT_ABS="${output_parent}/${output_name}"
	project_root=$(canonical_existing_path "${PROJECT_DIR}")

	if [ "${OUTPUT_ABS}" = "/" ] || [ "${OUTPUT_ABS}" = "${project_root}" ]; then
		echo "OUTPUT_DIR must not be the filesystem or project root." >&2
		return 1
	fi

	for protected in "${PAGES_DIR}" "${TEMPLATE_DIR}" "${STATIC_DIR}" "${HELPERS_DIR}"; do
		[ -e "${protected}" ] || continue
		protected_path=$(canonical_existing_path "${protected}")
		case "${OUTPUT_ABS}" in
			"${protected_path}"|"${protected_path}/"*)
				echo "OUTPUT_DIR must not overlap a source directory: ${protected}" >&2
				return 1
				;;
		esac
		case "${protected_path}" in
			"${OUTPUT_ABS}/"*)
				echo "OUTPUT_DIR must not contain a source directory: ${protected}" >&2
				return 1
				;;
		esac
	done
}

resolve_search_setting()
{
	case "${SEARCH_ENABLED}" in
		1|true|yes) SEARCH_ENABLED=1 ;;
		0|false|no) SEARCH_ENABLED=0 ;;
		auto)
			if command -v "${SMG_SEARCH}" >/dev/null 2>&1 || [ -f "${STATIC_DIR}/search.bin" ]; then
				SEARCH_ENABLED=1
			else
				SEARCH_ENABLED=0
			fi
			;;
		*) echo "SEARCH_ENABLED must be auto, 1, or 0." >&2; return 1 ;;
	esac
	export SEARCH_ENABLED
}

prepare_search_reader()
{
	local destination_dir=$1
	local relative reader_path

	[ "${SEARCH_ENABLED}" = "1" ] || return 0
	case "${SEARCH_READER}" in
		http://*|https://*|//*) return 0 ;;
	esac

	relative=${SEARCH_READER#/}
	if [ -z "${relative}" ] || [[ /${relative}/ == *"/../"* ]]; then
		echo "Invalid SEARCH_READER path: ${SEARCH_READER}" >&2
		return 1
	fi
	reader_path="${destination_dir}/${relative}"
	if [ ! -f "${reader_path}" ] && [ "${relative}" = "vendor/smgen-search/SearchReader.mjs" ]; then
		mkdir -p "${destination_dir}/vendor/smgen-search"
		copy_static_tree "${SMGEN_CORE_STATIC_DIR}/vendor/smgen-search" "${destination_dir}/vendor/smgen-search"
	fi
	if [ ! -f "${reader_path}" ]; then
		echo "Search reader not found in static output: ${SEARCH_READER}" >&2
		return 1
	fi
}

build_site()
{
	local requested_path=${1:-}
	local output_parent output_name backup_path="" page_file relative_path
	local directory base extension page_name destination template toc_value
	local template_file style inline_style pages_root
	local BUILD_DIR="" WORK_DIR="" NAV_DATA_FILE="" PAGE_MANIFEST="" OUTPUT_ABS=""
	local new_output_installed=0 build_committed=0
	local -a php_args pandoc_args
	local -A output_destinations=()

	require_command "${PHP}" "php"
	require_command "${YQ}" "yq"
	require_command "${PANDOC}" "pandoc"
	if ! "${PHP}" -r 'exit(extension_loaded("yaml") ? 0 : 1);'; then
		echo "The PHP YAML extension is required." >&2
		return 1
	fi

	[ -d "${PAGES_DIR}" ] || { echo "PAGES_DIR does not exist: ${PAGES_DIR}" >&2; return 1; }
	[ -d "${TEMPLATE_DIR}" ] || { echo "TEMPLATE_DIR does not exist: ${TEMPLATE_DIR}" >&2; return 1; }
	[ -z "${requested_path}" ] || validate_page_argument "${requested_path}"
	validate_output_path
	resolve_search_setting
	pages_root=$(canonical_existing_path "${PAGES_DIR}")

	if [ -f ./before-smgen.sh ]; then
		bash ./before-smgen.sh
	fi

	output_parent=$(dirname "${OUTPUT_ABS}")
	output_name=$(basename "${OUTPUT_ABS}")
	cleanup_build()
	{
		if [ "${new_output_installed}" = "1" ] && [ "${build_committed}" != "1" ]; then
			rm -rf -- "${OUTPUT_ABS}"
			[ -z "${backup_path}" ] || [ ! -e "${backup_path}" ] || mv "${backup_path}" "${OUTPUT_ABS}"
		fi
		[ -z "${BUILD_DIR:-}" ] || rm -rf -- "${BUILD_DIR}"
		[ -z "${WORK_DIR:-}" ] || rm -rf -- "${WORK_DIR}"
		trap - RETURN EXIT
	}
	trap cleanup_build RETURN EXIT
	BUILD_DIR=$(mktemp -d "${output_parent}/.${output_name}.smgen-build.XXXXXX")
	chmod 0755 "${BUILD_DIR}"
	WORK_DIR=$(mktemp -d "${TMPDIR:-/tmp}/smgen.XXXXXX")

	if [ -n "${CORE_ASSETS}" ]; then
		copy_selected_static_assets "${SMGEN_CORE_STATIC_DIR}" "${BUILD_DIR}" "${CORE_ASSETS}"
	elif [ "${INHERIT_CORE_STATIC}" = "1" ]; then
		copy_static_tree "${SMGEN_CORE_STATIC_DIR}" "${BUILD_DIR}"
	fi
	copy_static_tree "${STATIC_DIR}" "${BUILD_DIR}"
	prepare_search_reader "${BUILD_DIR}"

	NAV_DATA_FILE="${WORK_DIR}/navigation.json"
	PAGE_MANIFEST="${WORK_DIR}/pages.tsv"
	: > "${PAGE_MANIFEST}"
	"${PHP}" "${SCRIPT_DIR}/helpers/navigation-data.php" "${PAGES_DIR}" > "${NAV_DATA_FILE}"
	export SMGEN_NAV_DATA="${NAV_DATA_FILE}"

	php_args=(
		-d display_errors=stderr
		-d "auto_prepend_file=${SCRIPT_DIR}/helpers/bootstrap.php"
		-d "include_path=${SCRIPT_DIR}/helpers"
	)

	echo -e "\e[33;4mBuilding pages...\e[0m"
	while IFS= read -r -d $'\0' page_file; do
		relative_path=${page_file#"${pages_root}"/}
		[ "${relative_path}" != ".fm.yaml" ] || continue
		[[ ${relative_path} != */.fm.yaml ]] || continue

		directory=$(dirname "${relative_path}")
		base=$(basename "${relative_path}")
		extension=${base##*.}
		page_name=${base%.*}
		if [ "${directory}" = "." ]; then
			destination="${page_name}.html"
		else
			destination="${directory}/${page_name}.html"
		fi
		if [[ ${destination} == *$'\n'* || ${destination} == *$'\t'* ]]; then
			echo "Page paths must not contain tabs or newlines: ${relative_path}" >&2
			return 1
		fi
		if [ "${output_destinations[${destination}]+set}" = "set" ]; then
			echo "Multiple pages map to the same output: ${destination}" >&2
			return 1
		fi
		output_destinations["${destination}"]=${page_file}
		mkdir -p "${BUILD_DIR}/$(dirname "${destination}")"
		echo -e "\e[37m  ${page_file}...\e[0m"

		template=$(frontmatter_value "${page_file}" '.template // ""')
		if [ -n "${template}" ] && [ "${template}" != "null" ]; then
			template=$(resolve_template_path "${template}") || {
				echo "Template must be inside ${TEMPLATE_DIR}: ${template}" >&2
				return 1
			}
		else
			template="${TEMPLATE_DIR}/page.php"
			[ ! -f "${TEMPLATE_DIR}/${extension}-page.php" ] || template="${TEMPLATE_DIR}/${extension}-page.php"
			[ ! -f "${TEMPLATE_DIR}/${extension}-template.html" ] || template="${TEMPLATE_DIR}/${extension}-template.html"
		fi
		template=$(resolve_template_path "${template}") || {
			echo "Template must be inside ${TEMPLATE_DIR}: ${template}" >&2
			return 1
		}

		toc_value=$(frontmatter_value "${page_file}" '.TOC')
		if [ -z "${toc_value}" ] || [ "${toc_value}" = "null" ]; then
			toc_value=$(frontmatter_value "${page_file}" '.toc')
		fi
		if [ -z "${toc_value}" ] || [ "${toc_value}" = "null" ]; then
			toc_value=true
		fi
		template_file=$(mktemp "${WORK_DIR}/template.XXXXXX.html")

		CURRENT_PAGE="/${destination}" \
		SMGEN_PAGE_FILE="${page_file}" \
		SMGEN_CORE_DIR="${SCRIPT_DIR}" \
		SMGEN_PROJECT_DIR="${PROJECT_DIR}" \
		SMGEN_PROJECT_HELPERS_DIR="${HELPERS_DIR}" \
		"${PHP}" "${php_args[@]}" "${template}" "${page_file}" > "${template_file}"

		pandoc_args=(--data-dir=. -s -f markdown -t html)
		[ -z "${HIGHLIGHT_STYLE}" ] || pandoc_args+=(--highlight-style "${HIGHLIGHT_STYLE}")
		[ "${toc_value}" = "false" ] || pandoc_args+=(--toc)
		[ -z "${TITLE_PREFIX}" ] || pandoc_args+=(--title-prefix "${TITLE_PREFIX}")
		pandoc_args+=(--lua-filter="$(resolve_project_helper_path domain.lua)")
		pandoc_args+=(--template="${template_file}")
		while IFS= read -r inline_style; do
			[ -n "${inline_style}" ] && pandoc_args+=(-H "${inline_style}")
		done <<< "${INLINE_STYLES}"
		while IFS= read -r style; do
			[ -n "${style}" ] && pandoc_args+=(--css "$(resolve_asset_url "${style}")")
		done <<< "${STYLES}"
		pandoc_args+=(-o "${BUILD_DIR}/${destination}" "${page_file}")
		BASE_URL="${BASE_URL}" "${PANDOC}" "${pandoc_args[@]}"
		printf '%s\t%s\n' "${destination}" "${page_file}" >> "${PAGE_MANIFEST}"
	done < <(find "${pages_root}" -type f -print0)

	if [ "${SEARCH_ENABLED}" = "1" ] && command -v "${SMG_SEARCH}" >/dev/null 2>&1; then
		echo -e "\e[33;4mAssembling search index...\e[0m"
		"${SMG_SEARCH}" build-index "${PAGES_DIR}" "${BUILD_DIR}/search.bin"
	elif [ "${SEARCH_ENABLED}" = "1" ] && [ ! -f "${BUILD_DIR}/search.bin" ]; then
		echo "Search is enabled, but no search index or smgen-search executable is available." >&2
		return 1
	fi

	echo -e "\e[33;4mAssembling sitemap...\e[0m"
	"${PHP}" "${php_args[@]}" "$(resolve_project_helper_path sitemap.php)" "${BASE_URL}" "${BUILD_DIR}" "${PAGE_MANIFEST}" > "${BUILD_DIR}/sitemap.xml"

	if [ -e "${OUTPUT_ABS}" ]; then
		backup_path=$(mktemp -d "${output_parent}/.${output_name}.smgen-backup.XXXXXX")
		rmdir "${backup_path}"
		mv "${OUTPUT_ABS}" "${backup_path}"
	fi

	if ! mv "${BUILD_DIR}" "${OUTPUT_ABS}"; then
		[ -z "${backup_path}" ] || mv "${backup_path}" "${OUTPUT_ABS}"
		return 1
	fi
	BUILD_DIR=""
	new_output_installed=1

	if [ -f ./after-smgen.sh ] && ! bash ./after-smgen.sh; then
		echo "after-smgen.sh failed; restored the previous output." >&2
		return 1
	fi

	build_committed=1
	[ -z "${backup_path}" ] || rm -rf -- "${backup_path}"
	rm -rf -- "${WORK_DIR}"
	WORK_DIR=""
	trap - RETURN EXIT
}

watch_site()
{
	local server_pid filepath event candidate
	local -a watch_paths

	require_command "${PHP}" "php"
	require_command inotifywait "inotifywait (inotify-tools)"
	build_site

	echo "Serving on http://localhost:${DEV_PORT}; press ctrl+c to exit."
	"${PHP}" -S "localhost:${DEV_PORT}" -t "${OUTPUT_DIR}" &
	server_pid=$!
	trap 'kill "${server_pid}" 2>/dev/null || true' EXIT
	trap 'exit 130' INT
	trap 'exit 143' TERM

	for candidate in "${PAGES_DIR}" "${STATIC_DIR}" "${TEMPLATE_DIR}" "${HELPERS_DIR}" \
		"${PROJECT_DIR}/.smgen.yaml" "${PROJECT_DIR}/.smgen-rc" "${PROJECT_DIR}/.env" \
		"${PROJECT_DIR}/before-smgen.sh" "${PROJECT_DIR}/after-smgen.sh"; do
		[ -e "${candidate}" ] || continue
		watch_paths+=("$(canonical_existing_path "${candidate}")")
	done
	inotifywait -m -r -e close_write,create,delete,move --format '%w%f|%e' "${watch_paths[@]}" 2>/dev/null |
	while IFS='|' read -r filepath event; do
		echo "Change detected: ${filepath} (${event})"
		if ! "${BASH_SOURCE[0]}" build; then
			echo "Rebuild failed; continuing to serve the last successful output." >&2
		fi
	done

	wait "${server_pid}"
}

COMMAND=${1:-help}
case "${COMMAND}" in
	help|-h|--help)
		echo "Usage: $(basename "$0") <command>"
		echo
		echo "Commands:"
		echo "  init                Initialize a new site in current directory"
		echo "  build [path]        Build the complete site atomically"
		echo "  watch               Build, serve, and rebuild on filesystem changes"
		echo "  serve               Serve the generated output locally"
		echo "  proofread [path]    Proofread one or all documents"
		echo "  create-random-page  Create a random lorem Markdown page"
		echo "  help                Show this help message"
		;;

	init|i)
		load_config
		if [ -n "$(ls -A ./)" ]; then
			echo "Directory is not empty." >&2
			exit 1
		fi
		require_command curl
		mkdir -p "${OUTPUT_DIR}" pages static templates
		cp "${SCRIPT_DIR}/templates/page.php" "${SCRIPT_DIR}/templates/header.php" "${SCRIPT_DIR}/templates/footer.php" templates/
		cp "${SCRIPT_DIR}/static/default.css" "${SCRIPT_DIR}/static/main.js" "${SCRIPT_DIR}/static/search.js" static/
		cp "${SCRIPT_DIR}/static/search.svg" "${SCRIPT_DIR}/static/github.svg" "${SCRIPT_DIR}/static/logo.svg" static/
		cp "${SCRIPT_DIR}/static/sun.svg" "${SCRIPT_DIR}/static/moon.svg" static/
		cp "${SCRIPT_DIR}/static/icon-16.png" "${SCRIPT_DIR}/static/sitemap-badge.png" static/
		mkdir -p static/vendor
		cp -Rp "${SCRIPT_DIR}/static/vendor/smgen-search" static/vendor/
		cp "${SCRIPT_DIR}/.smgen-rc-default" ./.smgen-rc
		curl -fsSL "https://jaspervdj.be/lorem-markdownum/markdown.txt?p=5" -o ./pages/index.md
		;;

	build|b)
		load_config
		build_site "${2:-}"
		;;

	watch|w)
		load_config
		watch_site
		;;

	serve|s)
		load_config
		require_command "${PHP}" "php"
		[ -d "${OUTPUT_DIR}" ] || { echo "OUTPUT_DIR does not exist; run smgen build first." >&2; exit 1; }
		echo "Serving on http://localhost:${DEV_PORT}; press ctrl+c to exit."
		"${PHP}" -S "localhost:${DEV_PORT}" -t "${OUTPUT_DIR}"
		;;

	proofread|pr)
		load_config
		require_command "${ASPELL}" "aspell"
		ASPELL_FLAGS=(-t --home-dir=./ --personal="${ASPELL_DICT}")
		if [ "$#" -gt 1 ]; then
			"${ASPELL}" "${ASPELL_FLAGS[@]}" list < "${2}" | sort -u
		else
			CURRENT_CODE=0
			while IFS= read -r -d $'\0' FILE; do
				if "${ASPELL}" "${ASPELL_FLAGS[@]}" list < "${FILE}" | grep -q .; then
					CURRENT_CODE=1
					echo "${FILE} needs proofreading."
				fi
			done < <(find "${PAGES_DIR}" -type f -name '*.md' -print0)
			exit "${CURRENT_CODE}"
		fi
		;;

	create-random-page)
		load_config
		require_command curl
		[ -r /usr/share/dict/words ] || { echo "/usr/share/dict/words is required." >&2; exit 1; }
		PAGE_WORD=$(awk 'BEGIN { srand() } NF { n++; if(rand() < 1/n) word=$0 } END { print word }' /usr/share/dict/words | tr -cd '[:alnum:]_-')
		[ -n "${PAGE_WORD}" ] || { echo "Unable to choose a word from /usr/share/dict/words." >&2; exit 1; }
		PAGE_FILE="${PAGES_DIR}/${PAGE_WORD}.md"
		[ ! -e "${PAGE_FILE}" ] || { echo "File exists: ${PAGE_FILE}" >&2; exit 1; }
		curl -fsSL "https://jaspervdj.be/lorem-markdownum/markdown.txt?p=5" -o "${PAGE_FILE}"
		;;

	*)
		echo "Unknown command: ${COMMAND}" >&2
		echo "Run '$(basename "$0") help' for usage." >&2
		exit 1
		;;
esac
