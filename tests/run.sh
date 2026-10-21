#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )/.." &> /dev/null && pwd )"
SMGEN="${ROOT_DIR}/smgen.sh"
DEBUG_DIR="${SMGEN_TEST_DEBUG_DIR:-${ROOT_DIR}/.ci-debug}"
CURRENT_TEST=""
CURRENT_SITE=""
TEMP_ROOT="$(mktemp -d)"
DEBUG_ENTRIES=()

trap 'rm -rf -- "${TEMP_ROOT}"' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

mkdir -p "${DEBUG_DIR}"
if [ "$(cd "${DEBUG_DIR}" && pwd -P)" = "${ROOT_DIR}" ] || [ "$(cd "${DEBUG_DIR}" && pwd -P)" = "/" ]; then
	echo "Unsafe SMGEN_TEST_DEBUG_DIR: ${DEBUG_DIR}" >&2
	exit 1
fi
shopt -s dotglob nullglob
DEBUG_ENTRIES=("${DEBUG_DIR}"/*)
shopt -u dotglob nullglob
[ "${#DEBUG_ENTRIES[@]}" -eq 0 ] || rm -rf -- "${DEBUG_ENTRIES[@]}"

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
	} > "${target_dir}/env.txt"

	if [[ -n "${CURRENT_SITE}" && -d "${CURRENT_SITE}" ]]; then
		cp -pr "${CURRENT_SITE}/." "${target_dir}/site/"
	fi
}

report_unexpected_failure()
{
	local status=$?
	local log
	local marker="${DEBUG_DIR}/.${CURRENT_TEST:-test-setup}.reported"
	local -a logs=()

	trap - ERR
	set +e
	if [ -e "${marker}" ]; then
		exit "${status}"
	fi
	: > "${marker}"
	collect_debug
	echo "FAIL: ${CURRENT_TEST:-test setup} exited with status ${status}" >&2
	shopt -s nullglob
	logs=("${DEBUG_DIR}/${CURRENT_TEST}"*.log)
	shopt -u nullglob
	for log in "${logs[@]}"; do
		echo "--- ${log} ---" >&2
		cat "${log}" >&2
	done
	exit "${status}"
}

trap report_unexpected_failure ERR

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

assert_directory_exists()
{
	local directory="$1"
	[[ -d "${directory}" ]] || fail "expected directory to exist: ${directory}"
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

assert_file_excludes()
{
	local file="$1"
	local pattern="$2"
	if grep -Fq -- "${pattern}" "${file}"; then
		fail "expected ${file} to exclude: ${pattern}"
	fi
}

begin_test()
{
	CURRENT_TEST="$1"
	CURRENT_SITE=""
}

make_temp_site()
{
	local temp_dir
	temp_dir="$(mktemp -d "${TEMP_ROOT}/site.XXXXXX")"
	mkdir -p "${temp_dir}/docs" "${temp_dir}/pages" "${temp_dir}/static" "${temp_dir}/templates"
	cp "${ROOT_DIR}/templates/"*.php "${temp_dir}/templates/"
	cp -Rp "${ROOT_DIR}/static/." "${temp_dir}/static/"
	rm -f "${temp_dir}/static/archive.css"
	cp "${ROOT_DIR}/.smgen-rc-default" "${temp_dir}/.smgen-rc"
	printf '%s\n' '---' 'title: Home' '---' '' '# Hello' > "${temp_dir}/pages/index.md"
	echo "${temp_dir}"
}

test_init_creates_a_buildable_project()
{
	begin_test "init-project"
	local site fake_bin
	site="$(mktemp -d "${TEMP_ROOT}/site.XXXXXX")"
	CURRENT_SITE="${site}"
	fake_bin="${TEMP_ROOT}/fake-bin"
	mkdir -p "${fake_bin}"
	cat > "${fake_bin}/curl" <<'EOF'
#!/usr/bin/env bash
while [ "$#" -gt 0 ]; do
	if [ "$1" = "-o" ]; then
		printf '%s\n' '---' 'title: Home' '---' '' '# Initialized' > "$2"
		exit 0
	fi
	shift
done
exit 1
EOF
	chmod +x "${fake_bin}/curl"

	(
		cd "${site}"
		PATH="${fake_bin}:${PATH}" OUTPUT_DIR=./public "${SMGEN}" init > "${DEBUG_DIR}/${CURRENT_TEST}-init.log" 2>&1
		OUTPUT_DIR=./public "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-build.log" 2>&1
	)

	assert_directory_exists "${site}/public"
	assert_file_exists "${site}/public/index.html"
	assert_file_exists "${site}/static/vendor/smgen-search/SearchReader.mjs"
	assert_file_contains "${site}/public/index.html" 'Initialized'
	assert_file_missing "${site}/public/archive.css"
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
	assert_file_contains "${site}/docs/index.html" 'href="/default.css"'
	assert_file_contains "${site}/docs/index.html" 'src="/main.js"'
	assert_file_excludes "${site}/docs/index.html" 'http://localhost:8000/default.css'
	assert_file_excludes "${site}/docs/index.html" 'http://localhost:8000/main.js'
	assert_file_exists "${site}/docs/default.css"
	assert_file_exists "${site}/docs/main.js"
	assert_file_excludes "${site}/docs/index.html" '/archive.css'
	assert_file_missing "${site}/docs/archive.css"
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

test_env_base_url_overrides_same_origin_defaults()
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
	assert_file_contains "${site}/docs/index.html" 'href="https://seanmorris.github.io/smgen/index.html"'
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
<?php file_put_contents(__DIR__ . '/template-escape-executed.txt', 'executed'); ?>
EOF

	cat > "${site}/pages/index.md" <<EOF
---
title: Home
template: ${site}/evil.php
---

# Hello
EOF

	(
		cd "${site}"
		assert_command_fails "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}.log" 2>&1
	)

	assert_file_missing "${site}/template-escape-executed.txt"
}

test_project_helpers_override_core_helpers()
{
	begin_test "project-helper-overrides"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"

	mkdir -p "${site}/helpers"

	cat > "${site}/helpers/navbar.php" <<'EOF'
<?php
function renderNavBar($path = NULL, $rootPath = NULL, $idPath = '')
{
	echo '<div id="custom-navbar">custom nav</div>';
}
EOF

	cat > "${site}/helpers/sitemap.php" <<'EOF'
<?php
echo "<custom-sitemap />\n";
EOF

	cat > "${site}/helpers/domain.lua" <<'EOF'
function Image (el)
	return el
end
EOF

	cat > "${site}/pages/index.md" <<'EOF'
---
title: Home
---

# Hello

![](logo.png)
EOF

	printf '%s\n' '<svg xmlns="http://www.w3.org/2000/svg"></svg>' > "${site}/static/logo.png"

	(
		cd "${site}"
		BASE_URL="https://example.com" "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}.log" 2>&1
	)

	assert_file_contains "${site}/docs/index.html" 'id="custom-navbar"'
	assert_file_contains "${site}/docs/sitemap.xml" '<custom-sitemap />'
	assert_file_contains "${site}/docs/index.html" 'src="logo.png"'
}

test_project_can_inherit_core_static_assets()
{
	begin_test "inherit-core-static"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"

	rm -f "${site}/static/main.js" "${site}/static/default.css" "${site}/static/search.svg"

	cat > "${site}/.smgen-rc" <<'EOF'
#!/usr/bin/env bash

INHERIT_CORE_STATIC=1
BASE_URL=${BASE_URL:-"http://localhost:8000"}

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

	assert_file_exists "${site}/docs/default.css"
	assert_file_exists "${site}/docs/main.js"
	assert_file_contains "${site}/docs/index.html" "${BASE_URL:-http://localhost:8000}/default.css"
	assert_file_contains "${site}/docs/index.html" "${BASE_URL:-http://localhost:8000}/main.js"
}

test_project_can_select_specific_core_assets()
{
	begin_test "select-core-assets"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"

	rm -f "${site}/static/main.js" "${site}/static/default.css" "${site}/static/search.svg"

	cat > "${site}/.smgen-rc" <<'EOF'
#!/usr/bin/env bash

BASE_URL=${BASE_URL:-"http://localhost:8000"}

CORE_ASSETS=$( cat <<-END
	default.css
	main.js
END
)

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

	assert_file_exists "${site}/docs/default.css"
	assert_file_exists "${site}/docs/main.js"
	assert_file_missing "${site}/docs/search.svg"
}

test_yaml_lists_and_environment_precedence()
{
	begin_test "yaml-precedence"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"
	rm "${site}/.smgen-rc"
	printf '%s\n' 'console.log("blocking")' > "${site}/static/blocking.js"

	cat > "${site}/.smgen.yaml" <<'EOF'
OUTPUT_DIR: ./public
PRODUCT_NAME: YAML product
SEARCH_ENABLED: 0
STYLES:
  - /default.css
SCRIPTS:
  - /blocking.js
DEFER_SCRIPTS:
  - /main.js
EOF
	cat > "${site}/.smgen.production.yaml" <<'EOF'
PRODUCT_NAME: Production product
EOF

	(
		cd "${site}"
		SMGEN_ENVIRONMENT=production PRODUCT_NAME="Caller product" BASE_URL="https://example.test/docs" \
			"${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}.log" 2>&1
	)

	assert_file_exists "${site}/public/index.html"
	assert_file_contains "${site}/public/index.html" 'Caller product'
	assert_file_contains "${site}/public/index.html" '<script src="https://example.test/docs/blocking.js"></script>'
	assert_file_contains "${site}/public/index.html" '<script defer src="https://example.test/docs/main.js"></script>'
	assert_file_contains "${site}/public/index.html" 'href="https://example.test/docs/default.css"'
}

test_frontmatter_controls_templates_navigation_and_toc()
{
	begin_test "frontmatter-controls"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"
	cat > "${site}/templates/minimal-header.php" <<'EOF'
<header id="custom-header">Header</header>
EOF
	cat > "${site}/templates/minimal-footer.php" <<'EOF'
<footer id="custom-footer">Footer</footer>
EOF
	printf '%b' '---\r\ntitle: Frontmatter\r\nheader: templates/minimal-header.php\r\nfooter: templates/minimal-footer.php\r\nleftBarShow: false\r\nTOC: false\r\n---\r\n\r\n# Heading\r\n' > "${site}/pages/index.md"

	(
		cd "${site}"
		"${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}.log" 2>&1
	)

	assert_file_contains "${site}/docs/index.html" 'id="custom-header"'
	assert_file_contains "${site}/docs/index.html" 'id="custom-footer"'
	assert_file_excludes "${site}/docs/index.html" '<nav class = "main">'
	assert_file_excludes "${site}/docs/index.html" 'class = "table-of-contents"'
}

test_successful_build_replaces_the_complete_output()
{
	begin_test "replace-output"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"
	printf '%s\n' stale > "${site}/docs/stale.txt"

	(
		cd "${site}"
		"${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}.log" 2>&1
	)

	assert_file_missing "${site}/docs/stale.txt"
	assert_file_exists "${site}/docs/index.html"
}

test_failed_build_preserves_previous_output()
{
	begin_test "failed-build-rollback"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"
	(
		cd "${site}"
		"${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-first.log" 2>&1
	)
	assert_file_contains "${site}/docs/index.html" '<h1 id="hello">Hello</h1>'

	cat > "${site}/pages/index.md" <<'EOF'
---
title: Broken
template: ../outside.php
---

# Replacement
EOF
	(
		cd "${site}"
		assert_command_fails "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}.log" 2>&1
	)

	assert_file_contains "${site}/docs/index.html" '<h1 id="hello">Hello</h1>'
	assert_file_excludes "${site}/docs/index.html" 'Replacement'
}

test_early_build_failure_reports_original_error()
{
	begin_test "early-build-failure"
	local site fake_bin
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"
	fake_bin="${TEMP_ROOT}/failing-mktemp-bin"
	mkdir -p "${fake_bin}"
	cat > "${fake_bin}/mktemp" <<'EOF'
#!/usr/bin/env bash
echo "simulated mktemp failure" >&2
exit 12
EOF
	chmod +x "${fake_bin}/mktemp"

	(
		cd "${site}"
		assert_command_fails env PATH="${fake_bin}:${PATH}" "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}.log" 2>&1
	)

	assert_file_contains "${DEBUG_DIR}/${CURRENT_TEST}.log" 'simulated mktemp failure'
	assert_file_excludes "${DEBUG_DIR}/${CURRENT_TEST}.log" 'unbound variable'
}

test_build_uses_portable_mktemp_templates()
{
	begin_test "portable-mktemp-templates"
	local site fake_bin real_mktemp
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"
	printf '%s\n' '---' 'title: Second' '---' '' '# Second' > "${site}/pages/second.md"
	fake_bin="${TEMP_ROOT}/portable-mktemp-bin"
	real_mktemp="$(command -v mktemp)"
	mkdir -p "${fake_bin}"
	cat > "${fake_bin}/mktemp" <<'EOF'
#!/usr/bin/env bash
template=${!#}
if [[ ${template} != *X ]]; then
	echo "mktemp template must end in X: ${template}" >&2
	exit 64
fi
exec "${REAL_MKTEMP}" "$@"
EOF
	chmod +x "${fake_bin}/mktemp"

	(
		cd "${site}"
		PATH="${fake_bin}:${PATH}" REAL_MKTEMP="${real_mktemp}" "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}.log" 2>&1
	)

	assert_file_exists "${site}/docs/index.html"
	assert_file_exists "${site}/docs/second.html"
}

test_page_argument_still_rebuilds_the_complete_site()
{
	begin_test "page-argument-full-build"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"
	printf '%s\n' '---' 'title: Second' '---' '' '# Initial second' > "${site}/pages/second.md"
	(
		cd "${site}"
		"${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-first.log" 2>&1
	)
	printf '%s\n' '---' 'title: Home' '---' '' '# Updated home' > "${site}/pages/index.md"
	printf '%s\n' '---' 'title: Second' '---' '' '# Updated second' > "${site}/pages/second.md"

	(
		cd "${site}"
		"${SMGEN}" build pages/index.md > "${DEBUG_DIR}/${CURRENT_TEST}-relative.log" 2>&1
		"${SMGEN}" build "${site}/pages/second.md" > "${DEBUG_DIR}/${CURRENT_TEST}-absolute.log" 2>&1
	)

	assert_file_contains "${site}/docs/index.html" 'Updated home'
	assert_file_contains "${site}/docs/second.html" 'Updated second'
	assert_file_missing "${site}/docs/pages/index.html"
}

test_hooks_run_and_after_failure_rolls_back()
{
	begin_test "hooks-and-rollback"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"
	cat > "${site}/before-smgen.sh" <<'EOF'
printf 'before\n' >> hook.log
EOF
	cat > "${site}/after-smgen.sh" <<'EOF'
test -f docs/index.html
printf 'after\n' >> hook.log
EOF
	(
		cd "${site}"
		"${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-first.log" 2>&1
	)
	assert_file_contains "${site}/hook.log" 'before'
	assert_file_contains "${site}/hook.log" 'after'

	printf '%s\n' '---' 'title: Home' '---' '' '# Replacement' > "${site}/pages/index.md"
	cat > "${site}/after-smgen.sh" <<'EOF'
exit 7
EOF
	(
		cd "${site}"
		assert_command_fails "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-failure.log" 2>&1
	)
	assert_file_contains "${site}/docs/index.html" '<h1 id="hello">Hello</h1>'
	assert_file_excludes "${site}/docs/index.html" 'Replacement'
}

test_custom_template_directory_is_supported()
{
	begin_test "custom-template-directory"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"
	mv "${site}/templates" "${site}/views"
	cat > "${site}/.smgen-rc" <<'EOF'
BASE_URL=${BASE_URL:-http://localhost:8000}
TEMPLATE_DIR=./views
HEADER=views/header.php
FOOTER=views/footer.php
SEARCH_ENABLED=0
EOF
	(
		cd "${site}"
		"${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}.log" 2>&1
	)
	assert_file_exists "${site}/docs/index.html"
	assert_file_contains "${site}/docs/index.html" '<!DOCTYPE HTML>'
}

test_unsafe_output_and_core_asset_paths_are_rejected()
{
	begin_test "unsafe-paths"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"
	(
		cd "${site}"
		assert_command_fails env OUTPUT_DIR=./pages "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-output.log" 2>&1
		assert_command_fails env OUTPUT_DIR=./pages/generated "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-nested-output.log" 2>&1
		assert_command_fails env CORE_ASSETS=../main.js "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-asset.log" 2>&1
	)
	assert_file_exists "${site}/pages/index.md"
}

test_search_is_optional_local_and_lazy()
{
	begin_test "search-loading"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"
	(
		cd "${site}"
		"${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-disabled.log" 2>&1
	)
	assert_file_excludes "${site}/docs/index.html" 'id="search-query"'
	assert_file_excludes "${ROOT_DIR}/static/search.js" 'cdn.jsdelivr.net'
	assert_file_contains "${ROOT_DIR}/static/search.js" 'import(readerUrl)'

	printf 'SRCH' > "${site}/static/search.bin"
	rm -rf "${site}/static/vendor"
	(
		cd "${site}"
		SEARCH_ENABLED=1 "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-enabled.log" 2>&1
	)
	assert_file_contains "${site}/docs/index.html" 'id="search-query"'
	assert_file_contains "${site}/docs/index.html" 'data-search-reader="/vendor/smgen-search/SearchReader.mjs"'
	assert_file_exists "${site}/docs/vendor/smgen-search/SearchReader.mjs"
}

test_sitemap_encodes_paths_and_navigation_escapes_titles()
{
	begin_test "escaped-output"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"
	cat > "${site}/pages/a page.md" <<'EOF'
---
title: "<script>alert(1)</script>"
---

# Safe
EOF
	(
		cd "${site}"
		BASE_URL=https://example.test "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}.log" 2>&1
	)
	assert_file_contains "${site}/docs/sitemap.xml" 'https://example.test/a%20page.html'
	assert_file_contains "${site}/docs/index.html" '&lt;script&gt;alert(1)&lt;/script&gt;'
	assert_file_excludes "${site}/docs/index.html" '<script>alert(1)</script>'
}

test_archive_navigation_builds_static_calendar_and_timeline()
{
	begin_test "archive-navigation"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"

	cat >> "${site}/.smgen-rc" <<'EOF'

ARCHIVE_TITLE="Publication timeline"
NAVIGATION_WIDGETS=$(cat <<-END
	tree
	archive
END
)
EOF
	rm -f "${site}/static/archive.css"

	cat > "${site}/pages/latest.md" <<'EOF'
---
title: Latest post
date: 2026-10-21T18:30:00Z
leftBarLink: false
---

# Latest
EOF
	cat > "${site}/pages/same-day.md" <<'EOF'
---
title: Earlier that day
date: 2026-10-21T08:00:00Z
leftBarLink: false
---

# Same day
EOF
	cat > "${site}/pages/september.md" <<'EOF'
---
title: September post
date: 2026-09-03
leftBarLink: false
---

# September
EOF
	cat > "${site}/pages/older.md" <<'EOF'
---
title: Older post
date: 2025-05-04
leftBarLink: false
---

# Older
EOF
	cat > "${site}/pages/private.md" <<'EOF'
---
title: Private dated page
date: 2026-10-20
archive: false
leftBarLink: false
---

# Private
EOF

	(
		cd "${site}"
		BASE_URL=https://example.test/blog "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}.log" 2>&1
	)

	assert_file_contains "${site}/docs/index.html" 'aria-label="Publication timeline"'
	assert_file_contains "${site}/docs/index.html" 'href="https://example.test/blog/archive.css"'
	assert_file_exists "${site}/docs/archive.css"
	assert_file_contains "${site}/docs/index.html" 'class="archive-calendar"'
	assert_file_contains "${site}/docs/index.html" '>October 2026 publication calendar</caption>'
	assert_file_contains "${site}/docs/index.html" 'data-id-path="archive-2026"'
	assert_file_contains "${site}/docs/index.html" 'data-id-path="archive-2026-10"'
	assert_file_contains "${site}/docs/index.html" '<span class="archive-count">(3)</span>'
	assert_file_contains "${site}/docs/index.html" 'aria-label="October 21, 2026: 2 posts"'
	assert_file_contains "${site}/docs/index.html" '<span class="archive-day-count" aria-hidden="true">2</span>'
	assert_file_contains "${site}/docs/index.html" 'href="https://example.test/blog/latest.html"'
	assert_file_contains "${site}/docs/index.html" 'Latest post'
	assert_file_contains "${site}/docs/index.html" '>Home'
	assert_file_excludes "${site}/docs/index.html" 'Private dated page'

	assert_file_contains "${site}/docs/older.html" '>May 2025 publication calendar</caption>'
	assert_file_contains "${site}/docs/older.html" '<details open data-id-path="archive-2025"'
	assert_file_contains "${site}/docs/older.html" '<details open data-id-path="archive-2025-05"'
	assert_file_contains "${site}/docs/older.html" 'aria-current="date"'
	assert_file_contains "${site}/docs/older.html" 'class="active-link" aria-current="page"'
	assert_file_excludes "${site}/docs/older.html" 'archive.js'
	assert_file_excludes "${ROOT_DIR}/static/default.css" '.archive-calendar'
}

test_archive_navigation_rejects_invalid_dates()
{
	begin_test "archive-invalid-date"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"
	printf '\nNAVIGATION_WIDGETS=archive\n' >> "${site}/.smgen-rc"
	cat > "${site}/pages/broken.md" <<'EOF'
---
title: Broken date
date: definitely-not-a-date
---

# Broken
EOF

	(
		cd "${site}"
		assert_command_fails "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}.log" 2>&1
	)
	assert_file_contains "${DEBUG_DIR}/${CURRENT_TEST}.log" 'Invalid archive date'
}

test_named_menus_plugins_and_timeline_backfill()
{
	begin_test "named-menus"
	local site checksum
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"
	rm "${site}/.smgen-rc"
	mkdir -p "${site}/pages/guides" "${site}/plugins/example"

	cat > "${site}/.smgen.yaml" <<'EOF'
BASE_URL: https://example.test
SEARCH_ENABLED: 0
STYLES:
  - /default.css
PLUGINS:
  - plugins/example/smgen.plugin.yaml
MENUS:
  primary:
    label: Primary
    renderer: flat
    links:
      - id: site.external
        href: https://example.org
        title: External
        variant: cta
  documentation:
    label: Documentation
    renderer: tree
    hierarchy: source
  archive:
    label: Site Archive
    renderer: timeline
MENU_REGIONS:
  header: [primary]
  sidebar: [documentation, archive]
EOF

	cat > "${site}/plugins/example/smgen.plugin.yaml" <<'EOF'
id: example
menus:
  documentation:
    links:
      - id: plugin.guides
        group: true
        title: Plugin Guides
        weight: -10
    selectors:
      - include: ["guides/**/*.md"]
        where:
          audience: public
        defaults:
          parent: plugin.guides
alter: menu-alter.php
EOF
	cat > "${site}/plugins/example/menu-alter.php" <<'EOF'
<?php
return static function(array &$registry): void {
	$registry['links']['primary']['site.external']['title'] = 'Altered External';
};
EOF

	cat > "${site}/pages/index.md" <<'EOF'
---
title: Home
modified: 2024-05-06T12:00:00Z
menus:
  primary:
    title: Start
    weight: -10
---

# Hello
EOF
	cat > "${site}/pages/guides/intro.md" <<'EOF'
---
title: Public Guide
audience: public
---

# Guide
EOF
	cat > "${site}/pages/guides/hidden.md" <<'EOF'
---
title: Hidden Guide
audience: public
menus:
  documentation: false
---

# Hidden
EOF
	cat > "${site}/pages/undated.md" <<'EOF'
---
title: Undated Post
menus:
  archive: true
---

# Undated
EOF

	(
		cd "${site}"
		"${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-first.log" 2>&1
	)

	assert_file_contains "${site}/docs/index.html" 'data-menu="primary"'
	assert_file_contains "${site}/docs/index.html" '>Start</span>'
	assert_file_contains "${site}/docs/index.html" 'Altered External'
	assert_file_contains "${site}/docs/index.html" 'menu-link--cta'
	assert_file_contains "${site}/docs/index.html" 'Plugin Guides'
	assert_file_contains "${site}/docs/index.html" 'Public Guide'
	assert_file_excludes "${site}/docs/index.html" 'Hidden Guide'
	assert_file_contains "${site}/docs/index.html" 'aria-label="Site Archive"'
	assert_file_contains "${site}/docs/index.html" 'Undated Post'
	assert_file_contains "${site}/docs/index.html" 'href="https://example.test/archive.css"'
	assert_file_exists "${site}/docs/archive.css"
	assert_file_excludes "${site}/docs/index.html" 'archive.js'
	assert_file_contains "${site}/pages/undated.md" "created: '"
	assert_file_excludes "${site}/pages/undated.md" 'modified:'
	assert_file_contains "${site}/docs/sitemap.xml" '<lastmod>2024-05-06</lastmod>'

	checksum="$(cksum "${site}/pages/undated.md")"
	(
		cd "${site}"
		"${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-second.log" 2>&1
	)
	[ "${checksum}" = "$(cksum "${site}/pages/undated.md")" ] || fail "timeline timestamp backfill was not idempotent"
}

test_named_menu_validation_preserves_output()
{
	begin_test "named-menu-validation"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"
	rm "${site}/.smgen-rc"
	cat > "${site}/.smgen.yaml" <<'EOF'
SEARCH_ENABLED: 0
MENUS:
  primary:
    renderer: flat
MENU_REGIONS:
  header: [primary]
EOF
	(
		cd "${site}"
		"${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-first.log" 2>&1
	)
	assert_file_contains "${site}/docs/index.html" '<h1 id="hello">Hello</h1>'

	cat > "${site}/.smgen.yaml" <<'EOF'
SEARCH_ENABLED: 0
MENUS:
  primary:
    renderer: flat
    links:
      - id: first
        group: true
        title: First
        parent: second
      - id: second
        group: true
        title: Second
        parent: first
MENU_REGIONS:
  header: [primary]
EOF
	(
		cd "${site}"
		assert_command_fails "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-failure.log" 2>&1
	)
	assert_file_contains "${DEBUG_DIR}/${CURRENT_TEST}-failure.log" 'Parent cycle in menu primary'
	assert_file_contains "${site}/docs/index.html" '<h1 id="hello">Hello</h1>'
}

test_external_links_use_noopener()
{
	begin_test "external-links-noopener"
	assert_file_contains "${ROOT_DIR}/static/main.js" "window.open(href, '_blank', 'noopener,noreferrer');"
}

test_logo_preserves_its_aspect_ratio()
{
	begin_test "logo-aspect-ratio"
	assert_file_contains "${ROOT_DIR}/static/default.css" $'a.logo img.logo-image {\n\twidth: 6rem;\n\theight: auto;'
}

main()
{
	test_init_creates_a_buildable_project
	test_default_build_generates_expected_output
	test_custom_output_dir_builds_and_links_sitemap
	test_env_base_url_overrides_same_origin_defaults
	test_nested_static_assets_preserve_paths_on_build
	test_page_template_cannot_escape_template_dir
	test_project_helpers_override_core_helpers
	test_project_can_inherit_core_static_assets
	test_project_can_select_specific_core_assets
	test_yaml_lists_and_environment_precedence
	test_frontmatter_controls_templates_navigation_and_toc
	test_successful_build_replaces_the_complete_output
	test_failed_build_preserves_previous_output
	test_early_build_failure_reports_original_error
	test_build_uses_portable_mktemp_templates
	test_page_argument_still_rebuilds_the_complete_site
	test_hooks_run_and_after_failure_rolls_back
	test_custom_template_directory_is_supported
	test_unsafe_output_and_core_asset_paths_are_rejected
	test_search_is_optional_local_and_lazy
	test_sitemap_encodes_paths_and_navigation_escapes_titles
	test_archive_navigation_builds_static_calendar_and_timeline
	test_archive_navigation_rejects_invalid_dates
	test_named_menus_plugins_and_timeline_backfill
	test_named_menu_validation_preserves_output
	test_external_links_use_noopener
	test_logo_preserves_its_aspect_ratio

	echo "PASS: all tests"
}

main "$@"
