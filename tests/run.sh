#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )/.." &> /dev/null && pwd )"
SMGEN="${ROOT_DIR}/smgen.sh"
DEBUG_DIR="${SMGEN_TEST_DEBUG_DIR:-${ROOT_DIR}/.ci-debug}"
CURRENT_TEST=""
CURRENT_SITE=""

rm -rf "${DEBUG_DIR}"
mkdir -p "${DEBUG_DIR}"

collect_debug()
{
	local target_dir

	if [[ -z "${CURRENT_TEST}" ]]; then
		return
	fi

	target_dir="${DEBUG_DIR}/${CURRENT_TEST}"
	mkdir -p "${target_dir}"

	{
		echo "test=${CURRENT_TEST}"
		echo "site=${CURRENT_SITE}"
		echo "pwd=$(pwd)"
		echo "BASE_URL=${BASE_URL:-}"
		echo "GITHUB_ENV=${GITHUB_ENV:-}"
		env | sort
	} > "${target_dir}/env.txt"

	if [[ -n "${CURRENT_SITE}" && -d "${CURRENT_SITE}" ]]; then
		cp -pr "${CURRENT_SITE}/." "${target_dir}/site/"
	fi
}

fail()
{
	collect_debug
	echo "FAIL: $*" >&2
	exit 1
}

assert_command_fails()
{
	if "$@"; then
		fail "expected command to fail: $*"
	fi
}

assert_file_exists()
{
	local file="$1"
	[[ -f "${file}" ]] || fail "expected file to exist: ${file}"
}

assert_file_missing()
{
	local file="$1"
	[[ ! -e "${file}" ]] || fail "expected file to be missing: ${file}"
}

assert_file_contains()
{
	local file="$1"
	local pattern="$2"
	grep -Fq -- "${pattern}" "${file}" || fail "expected ${file} to contain: ${pattern}"
}

begin_test()
{
	CURRENT_TEST="$1"
	CURRENT_SITE=""
}

make_temp_site()
{
	local temp_dir
	temp_dir="$(mktemp -d)"
	mkdir -p "${temp_dir}/docs" "${temp_dir}/pages" "${temp_dir}/static" "${temp_dir}/templates"
	cp "${ROOT_DIR}/templates/"*.php "${temp_dir}/templates/"
	cp "${ROOT_DIR}/static/"* "${temp_dir}/static/"
	cp "${ROOT_DIR}/.smgen-rc-default" "${temp_dir}/.smgen-rc"
	printf '%s\n' '---' 'title: Home' '---' '' '# Hello' > "${temp_dir}/pages/index.md"
	echo "${temp_dir}"
}

test_default_build_generates_expected_output()
{
	begin_test "default-build"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"

	(
		cd "${site}"
		"${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}.log" 2>&1
	)

	assert_file_exists "${site}/docs/index.html"
	assert_file_exists "${site}/docs/sitemap.xml"
	assert_file_missing "${site}/docs/sitemap.xml "
	assert_file_contains "${site}/docs/index.html" "<!DOCTYPE HTML>"
	assert_file_contains "${site}/docs/sitemap.xml" "<urlset"
}

test_custom_output_dir_builds_and_links_sitemap()
{
	begin_test "custom-output-dir"
	local site
	local expected_base_url
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"

	cat > "${site}/.smgen-rc" <<'EOF'
#!/usr/bin/env bash

DEV_PORT=8000
BASE_URL=${BASE_URL:-"http://localhost:${DEV_PORT}"}
DEFAULT_THEME=theme-default
OUTPUT_DIR=./public

STYLES=$( cat <<-END
	${BASE_URL}/default.css
END
)

SCRIPTS=$( cat <<-END
	${BASE_URL}/main.js
END
)
EOF

	(
		cd "${site}"
		"${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}.log" 2>&1
	)

	expected_base_url="${BASE_URL:-http://localhost:8000}"

	assert_file_exists "${site}/public/index.html"
	assert_file_exists "${site}/public/sitemap.xml"
	assert_file_missing "${site}/public/sitemap.xml "
	assert_file_contains "${site}/public/index.html" "${expected_base_url}/sitemap.xml"
}

test_env_base_url_overrides_localhost_defaults()
{
	begin_test "env-base-url"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"

	(
		cd "${site}"
		BASE_URL="https://seanmorris.github.io/smgen" "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}.log" 2>&1
	)

	assert_file_contains "${site}/docs/index.html" 'content="https://seanmorris.github.io/smgen"'
	assert_file_contains "${site}/docs/index.html" 'href="https://seanmorris.github.io/smgen/icon-16.png"'
	assert_file_contains "${site}/docs/index.html" 'href = "https://seanmorris.github.io/smgen/index.html"'
	assert_file_contains "${site}/docs/sitemap.xml" '<loc>https://seanmorris.github.io/smgen/index.html</loc>'
}

test_nested_static_assets_preserve_paths_on_build()
{
	begin_test "nested-static-assets"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"

	mkdir -p "${site}/static/images"
	printf '%s\n' '<svg xmlns="http://www.w3.org/2000/svg"></svg>' > "${site}/static/images/logo.svg"

	(
		cd "${site}"
		"${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}.log" 2>&1
	)

	assert_file_exists "${site}/docs/images/logo.svg"
	assert_file_contains "${site}/docs/index.html" 'class="active-link"'
	assert_file_contains "${site}/docs/index.html" '>Home'
	assert_file_missing "${site}/docs/writing-pages.html"
}

test_page_template_cannot_escape_template_dir()
{
	begin_test "template-escape"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"

	cat > "${site}/evil.php" <<'EOF'
<?php file_put_contents('/tmp/smgen-template-escape.txt', 'executed'); ?>
EOF

	cat > "${site}/pages/index.md" <<EOF
---
title: Home
template: ${site}/evil.php
---

# Hello
EOF

	rm -f /tmp/smgen-template-escape.txt

	(
		cd "${site}"
		assert_command_fails "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}.log" 2>&1
	)

	assert_file_missing /tmp/smgen-template-escape.txt
}

test_external_links_use_noopener()
{
	begin_test "external-links-noopener"
	assert_file_contains "${ROOT_DIR}/static/main.js" "window.open(href, '_blank', 'noopener,noreferrer');"
}

main()
{
	test_default_build_generates_expected_output
	test_custom_output_dir_builds_and_links_sitemap
	test_env_base_url_overrides_localhost_defaults
	test_nested_static_assets_preserve_paths_on_build
	test_page_template_cannot_escape_template_dir
	test_external_links_use_noopener

	echo "PASS: all tests"
}

main "$@"
