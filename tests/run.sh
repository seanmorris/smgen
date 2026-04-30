#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )/.." &> /dev/null && pwd )"
SMGEN="${ROOT_DIR}/smgen.sh"

fail()
{
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
	local site
	site="$(make_temp_site)"

	(
		cd "${site}"
		"${SMGEN}" build >/dev/null
	)

	assert_file_exists "${site}/docs/index.html"
	assert_file_exists "${site}/docs/sitemap.xml"
	assert_file_missing "${site}/docs/sitemap.xml "
	assert_file_contains "${site}/docs/index.html" "<!DOCTYPE HTML>"
	assert_file_contains "${site}/docs/sitemap.xml" "<urlset"
}

test_custom_output_dir_builds_and_links_sitemap()
{
	local site
	site="$(make_temp_site)"

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
		"${SMGEN}" build >/dev/null
	)

	assert_file_exists "${site}/public/index.html"
	assert_file_exists "${site}/public/sitemap.xml"
	assert_file_missing "${site}/public/sitemap.xml "
	assert_file_contains "${site}/public/index.html" 'href="/sitemap.xml"'
}

test_nested_static_assets_preserve_paths_on_build()
{
	local site
	site="$(make_temp_site)"

	mkdir -p "${site}/static/images"
	printf '%s\n' '<svg xmlns="http://www.w3.org/2000/svg"></svg>' > "${site}/static/images/logo.svg"

	(
		cd "${site}"
		"${SMGEN}" build >/dev/null
	)

	assert_file_exists "${site}/docs/images/logo.svg"
	assert_file_contains "${site}/docs/index.html" 'class="active-link"'
	assert_file_contains "${site}/docs/index.html" '>Home'
	assert_file_missing "${site}/docs/writing-pages.html"
}

test_page_template_cannot_escape_template_dir()
{
	local site
	site="$(make_temp_site)"

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
		assert_command_fails "${SMGEN}" build >/dev/null 2>&1
	)

	assert_file_missing /tmp/smgen-template-escape.txt
}

test_external_links_use_noopener()
{
	assert_file_contains "${ROOT_DIR}/static/main.js" "window.open(href, '_blank', 'noopener,noreferrer');"
}

main()
{
	test_default_build_generates_expected_output
	test_custom_output_dir_builds_and_links_sitemap
	test_nested_static_assets_preserve_paths_on_build
	test_page_template_cannot_escape_template_dir
	test_external_links_use_noopener

	echo "PASS: all tests"
}

main "$@"
