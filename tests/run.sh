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

assert_files_equal()
{
	local expected="$1"
	local actual="$2"
	diff -u "${expected}" "${actual}" || fail "expected files to match: ${expected} ${actual}"
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
	assert_file_excludes "${site}/docs/index.html" 'application/rss+xml'
	assert_file_excludes "${site}/docs/index.html" 'href="/llms.txt"'
	assert_file_missing "${site}/docs/archive.css"
	assert_file_missing "${site}/docs/llms.txt"
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

	cat > "${site}/helpers/rss.php" <<'EOF'
<?php
file_put_contents($argv[2] . '/custom.xml', "<custom-rss />\n");
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

	cat > "${site}/.smgen.yaml" <<'EOF'
RSS_FEEDS:
  custom:
    path: /custom.xml
    title: Custom feed
    description: Custom feed description
EOF

	printf '%s\n' '<svg xmlns="http://www.w3.org/2000/svg"></svg>' > "${site}/static/logo.png"

	(
		cd "${site}"
		BASE_URL="https://example.com" "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}.log" 2>&1
	)

	assert_file_contains "${site}/docs/index.html" 'id="custom-navbar"'
	assert_file_contains "${site}/docs/sitemap.xml" '<custom-sitemap />'
	assert_file_contains "${site}/docs/custom.xml" '<custom-rss />'
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

test_jsonld_frontmatter()
{
	begin_test "jsonld-frontmatter"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"

	cat > "${site}/pages/index.md" <<'EOF'
---
title: JSON-LD
itemtype: schema.org/TechArticle
microdata:
  headline: Legacy headline
jsonld:
  "@context": https://schema.org
  "@type": TechArticle
  headline: 'Cost is $10 and $title$'
  description: '</script><script>alert("jsonld")</script>'
  name: Café
  isAccessibleForFree: true
  wordCount: 12
  rating: 4.5
  author:
    "@type": Person
    name: 'Jane & "J.D." Doe'
  keywords:
    - static sites
    - documentation
---

# JSON-LD
EOF
	cat > "${site}/pages/multiple.md" <<'EOF'
---
title: Multiple entities
jsonld:
  - "@context": https://schema.org
    "@type": WebSite
    name: Example Site
  - "@context": https://schema.org
    "@type": Organization
    name: Example Organization
---

# Multiple entities
EOF
	cat > "${site}/pages/plain.md" <<'EOF'
---
title: Plain page
---

# Plain page
EOF

	(
		cd "${site}"
		"${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}.log" 2>&1
	)

	php -r '
		$html = file_get_contents($argv[1]);
		if(substr_count($html, "<script type=\"application/ld+json\">") !== 1) exit(1);
		if(!preg_match("~<script type=\"application/ld\\+json\">(.*?)</script>~s", $html, $match)) exit(2);
		if(strpos($html, $match[0]) > strpos($html, "</head>")) exit(3);
		$actual = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
		$expected = [
			"@context" => "https://schema.org",
			"@type" => "TechArticle",
			"headline" => "Cost is \$10 and \$title\$",
			"description" => "</script><script>alert(\"jsonld\")</script>",
			"name" => "Café",
			"isAccessibleForFree" => true,
			"wordCount" => 12,
			"rating" => 4.5,
			"author" => ["@type" => "Person", "name" => "Jane & \"J.D.\" Doe"],
			"keywords" => ["static sites", "documentation"],
		];
		exit($actual === $expected ? 0 : 4);
	' "${site}/docs/index.html" || fail "generated JSON-LD did not match page frontmatter"
	php -r '
		$html = file_get_contents($argv[1]);
		if(substr_count($html, "<script type=\"application/ld+json\">") !== 1) exit(1);
		if(!preg_match("~<script type=\"application/ld\\+json\">(.*?)</script>~s", $html, $match)) exit(2);
		$actual = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
		$expected = [
			["@context" => "https://schema.org", "@type" => "WebSite", "name" => "Example Site"],
			["@context" => "https://schema.org", "@type" => "Organization", "name" => "Example Organization"],
		];
		exit($actual === $expected ? 0 : 3);
	' "${site}/docs/multiple.html" || fail "generated JSON-LD did not preserve the entity list"
	assert_file_contains "${site}/docs/index.html" 'itemscope itemtype = "https://schema.org/TechArticle"'
	assert_file_contains "${site}/docs/index.html" 'itemprop = "headline" content = "Legacy headline"'
	assert_file_excludes "${site}/docs/index.html" '<script>alert("jsonld")</script>'
	assert_file_excludes "${site}/docs/plain.html" 'application/ld+json'

	cat > "${site}/pages/index.md" <<'EOF'
---
title: Invalid JSON-LD
jsonld:
---

# Replacement
EOF
	(
		cd "${site}"
		assert_command_fails "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-null.log" 2>&1
	)
	assert_file_contains "${DEBUG_DIR}/${CURRENT_TEST}-null.log" 'JSON-LD frontmatter must be a mapping or list of mappings:'
	assert_file_contains "${DEBUG_DIR}/${CURRENT_TEST}-null.log" 'pages/index.md'
	assert_file_contains "${site}/docs/index.html" 'Cost is $10 and $title$'
	assert_file_excludes "${site}/docs/index.html" 'Replacement'

	cat > "${site}/pages/index.md" <<'EOF'
---
title: Invalid JSON-LD list
jsonld:
  - "@context": https://schema.org
    "@type": WebSite
  - invalid
---

# Replacement
EOF
	(
		cd "${site}"
		assert_command_fails "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-list.log" 2>&1
	)
	assert_file_contains "${DEBUG_DIR}/${CURRENT_TEST}-list.log" 'JSON-LD frontmatter entry 2 must be a mapping:'
	assert_file_contains "${DEBUG_DIR}/${CURRENT_TEST}-list.log" 'pages/index.md'
	assert_file_contains "${site}/docs/index.html" 'Cost is $10 and $title$'
	assert_file_excludes "${site}/docs/index.html" 'Replacement'
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

test_llms_txt_uses_page_and_directory_frontmatter()
{
	begin_test "llms-txt"
	local site expected
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"
	mkdir -p "${site}/pages/guides"

	cat > "${site}/.smgen.yaml" <<'EOF'
SEARCH_ENABLED: 0
LLMS:
  title: Example [Docs]
  summary: |
    A small site index.
    Use the links below.
  details: |
    This index points to generated HTML.
  sections:
    guides:
      title: Guides
      weight: 20
    start:
      title: Start Here
      weight: 0
EOF
	cat > "${site}/pages/index.md" <<'EOF'
---
title: Home
description-meta: Read the home page.
description: This lower-priority description should not appear.
weight: 50
llms:
  section: start
  title: Start [here]
  weight: 10
---

# Home
EOF
	cat > "${site}/pages/quick start.md" <<'EOF'
---
llms: start
---

# Quick Start
EOF
	cat > "${site}/pages/guides/.fm.yaml" <<'EOF'
llms:
  section: guides
EOF
	cat > "${site}/pages/guides/setup.md" <<'EOF'
---
title: Setup
description: Install the tools, then build the site.
---

# Setup
EOF
	cat > "${site}/pages/guides/hidden.md" <<'EOF'
---
title: Hidden Guide
llms: false
---

# Hidden Guide
EOF

	(
		cd "${site}"
		BASE_URL=https://example.test/docs "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}.log" 2>&1
	)

	expected="${site}/expected-llms.txt"
	cat > "${expected}" <<'EOF'
# Example [Docs]

> A small site index.
> Use the links below.

This index points to generated HTML.

## Start Here

- [Quick Start](https://example.test/docs/quick%20start.html)
- [Start \[here\]](https://example.test/docs/index.html): Read the home page.

## Guides

- [Setup](https://example.test/docs/guides/setup.html): Install the tools, then build the site.
EOF
	assert_file_exists "${site}/docs/llms.txt"
	assert_files_equal "${expected}" "${site}/docs/llms.txt"
	assert_file_contains "${site}/docs/index.html" 'rel="describedby" type="text/markdown" href="https://example.test/docs/llms.txt"'
	assert_file_excludes "${site}/docs/llms.txt" 'Hidden Guide'
	assert_file_excludes "${site}/docs/llms.txt" 'lower-priority description'
}

test_llms_validation_preserves_previous_output()
{
	begin_test "llms-validation"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"
	(
		cd "${site}"
		"${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-first.log" 2>&1
	)

	cat > "${site}/.smgen.yaml" <<'EOF'
SEARCH_ENABLED: 0
LLMS:
  title: Example Docs
  sections:
    start:
      title: Start Here
EOF
	cat > "${site}/pages/index.md" <<'EOF'
---
title: Replacement
llms:
  section: missing
---

# Replacement
EOF
	(
		cd "${site}"
		assert_command_fails "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-section.log" 2>&1
	)
	assert_file_contains "${DEBUG_DIR}/${CURRENT_TEST}-section.log" 'Unknown llms.txt section missing'
	assert_file_contains "${site}/docs/index.html" '<h1 id="hello">Hello</h1>'
	assert_file_missing "${site}/docs/llms.txt"

	cat > "${site}/pages/index.md" <<'EOF'
---
title: Replacement
llms:
  section: start
---

# Replacement
EOF
	printf '%s\n' 'handwritten index' > "${site}/static/llms.txt"
	(
		cd "${site}"
		assert_command_fails "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-collision.log" 2>&1
	)
	assert_file_contains "${DEBUG_DIR}/${CURRENT_TEST}-collision.log" 'llms.txt collides with an existing output file'
	assert_file_contains "${site}/docs/index.html" '<h1 id="hello">Hello</h1>'

	rm "${site}/static/llms.txt"
	cat > "${site}/.smgen.yaml" <<'EOF'
SEARCH_ENABLED: 0
LLMS:
  title: Example Docs
  unknown: value
  sections:
    start:
      title: Start Here
EOF
	(
		cd "${site}"
		assert_command_fails "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-field.log" 2>&1
	)
	assert_file_contains "${DEBUG_DIR}/${CURRENT_TEST}-field.log" 'Unknown LLMS configuration field unknown'
	assert_file_contains "${site}/docs/index.html" '<h1 id="hello">Hello</h1>'
}

test_llms_heading_fallback_respects_fence_delimiters()
{
	begin_test "llms-heading-fences"
	local site
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"

	cat > "${site}/.smgen.yaml" <<'EOF'
SEARCH_ENABLED: 0
LLMS:
  title: Example Docs
  sections:
    start:
      title: Start Here
EOF
	cat > "${site}/pages/fence-length.md" <<'EOF'
---
llms: start
---

````
```
# Short fence is code
```
````

# Length-safe title
EOF
	cat > "${site}/pages/fence-suffix.md" <<'EOF'
---
llms: start
---

````
```` still code
# Trailing text is code
````

# Suffix-safe title
EOF

	(
		cd "${site}"
		BASE_URL=https://example.test/docs "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}.log" 2>&1
	)

	assert_file_contains "${site}/docs/llms.txt" '- [Length-safe title](https://example.test/docs/fence-length.html)'
	assert_file_contains "${site}/docs/llms.txt" '- [Suffix-safe title](https://example.test/docs/fence-suffix.html)'
	assert_file_excludes "${site}/docs/llms.txt" 'Short fence is code'
	assert_file_excludes "${site}/docs/llms.txt" 'Trailing text is code'
}

test_named_rss_feeds_use_page_flags_and_dates()
{
	begin_test "named-rss-feeds"
	local site item_count latest_line created_line
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"

	cat > "${site}/.smgen.yaml" <<'EOF'
RSS_FEEDS:
  main:
    path: /feed.xml
    title: Main & Updates
    description: Recent articles & notes
    link: /news/
    language: en-us
    limit: 2
    namespaces:
      media: http://search.yahoo.com/mrss/
    channel:
      copyright: Copyright 2026 & later
      category: [Engineering, Releases]
      image:
        url: https://example.test/feed.png
        title: Main feed image
        link: https://example.test/blog/news/
  releases:
    path: /feeds/releases.xml
    title: Releases
    description: Every release
    limit: 0
    namespaces:
      media: http://search.yahoo.com/mrss/
EOF
	cat > "${site}/pages/index.md" <<'EOF'
---
title: Home
date: 2026-10-20
feeds: [main]
---

# Home
EOF
	cat > "${site}/pages/latest update.md" <<'EOF'
---
title: Latest & greatest
description-meta: "Latest & <safe>"
date: 2026-10-24T12:30:00-04:00
feeds:
  main: true
  releases: true
rss:
  author: editor@example.test
  category:
    - _attributes:
        domain: https://example.test/topics
      _text: Release & notes
  enclosure:
    _attributes:
      url: https://example.test/audio.mp3?one=1&two=2
      length: 1234
      type: audio/mpeg
  media:content:
    _attributes:
      url: https://example.test/cover.jpg
      medium: image
---

# Latest
EOF
	cat > "${site}/pages/created.md" <<'EOF'
---
title: Created date
created: 2026-10-23
feeds: [main, releases]
---

# Created
EOF
	cat > "${site}/pages/older.md" <<'EOF'
---
title: Older release
date: 2025-05-04
feeds: [main, releases]
---

# Older
EOF
	cat > "${site}/pages/disabled.md" <<'EOF'
---
title: Disabled
feeds:
  main: false
---

# Disabled
EOF
	cat > "${site}/pages/unlisted.md" <<'EOF'
---
title: Unlisted
date: 2026-10-24
archive: false
---

# Unlisted
EOF

	(
		cd "${site}"
		BASE_URL=https://example.test/blog "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}.log" 2>&1
	)

	assert_file_exists "${site}/docs/feed.xml"
	assert_file_exists "${site}/docs/feeds/releases.xml"
	assert_file_contains "${site}/docs/index.html" 'type="application/rss+xml" title="Main &amp; Updates" href="https://example.test/blog/feed.xml"'
	assert_file_contains "${site}/docs/index.html" 'type="application/rss+xml" title="Releases" href="https://example.test/blog/feeds/releases.xml"'
	assert_file_contains "${site}/docs/feed.xml" '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/">'
	assert_file_contains "${site}/docs/feed.xml" '<title>Main &amp; Updates</title>'
	assert_file_contains "${site}/docs/feed.xml" '<link>https://example.test/blog/news/</link>'
	assert_file_contains "${site}/docs/feed.xml" '<atom:link href="https://example.test/blog/feed.xml" rel="self" type="application/rss+xml"/>'
	assert_file_contains "${site}/docs/feed.xml" '<language>en-us</language>'
	assert_file_contains "${site}/docs/feed.xml" '<copyright>Copyright 2026 &amp; later</copyright>'
	assert_file_contains "${site}/docs/feed.xml" '<category>Engineering</category>'
	assert_file_contains "${site}/docs/feed.xml" '<category>Releases</category>'
	assert_file_contains "${site}/docs/feed.xml" '<image>'
	assert_file_contains "${site}/docs/feed.xml" '<url>https://example.test/feed.png</url>'
	assert_file_contains "${site}/docs/feed.xml" '<lastBuildDate>Sat, 24 Oct 2026 16:30:00 +0000</lastBuildDate>'
	assert_file_contains "${site}/docs/feed.xml" '<title>Latest &amp; greatest</title>'
	assert_file_contains "${site}/docs/feed.xml" '<link>https://example.test/blog/latest%20update.html</link>'
	assert_file_contains "${site}/docs/feed.xml" '<pubDate>Sat, 24 Oct 2026 16:30:00 +0000</pubDate>'
	assert_file_contains "${site}/docs/feed.xml" '<description>Latest &amp; &lt;safe&gt;</description>'
	assert_file_contains "${site}/docs/feed.xml" '<author>editor@example.test</author>'
	assert_file_contains "${site}/docs/feed.xml" '<category domain="https://example.test/topics">Release &amp; notes</category>'
	assert_file_contains "${site}/docs/feed.xml" '<enclosure url="https://example.test/audio.mp3?one=1&amp;two=2" length="1234" type="audio/mpeg"/>'
	assert_file_contains "${site}/docs/feed.xml" '<media:content url="https://example.test/cover.jpg" medium="image"/>'
	assert_file_contains "${site}/docs/feed.xml" '<title>Created date</title>'
	assert_file_excludes "${site}/docs/feed.xml" 'Older release'
	assert_file_excludes "${site}/docs/feed.xml" '<title>Home</title>'
	assert_file_excludes "${site}/docs/feed.xml" 'Disabled'
	assert_file_excludes "${site}/docs/feed.xml" 'Unlisted'
	assert_file_contains "${site}/docs/feeds/releases.xml" '<title>Older release</title>'
	assert_file_contains "${site}/docs/feeds/releases.xml" '<media:content url="https://example.test/cover.jpg" medium="image"/>'
	assert_file_excludes "${site}/docs/feeds/releases.xml" '<title>Home</title>'
	item_count="$(grep -c '<item>' "${site}/docs/feed.xml")"
	[ "${item_count}" -eq 2 ] || fail "expected two items in limited RSS feed, found ${item_count}"
	latest_line="$(grep -n -m1 '<title>Latest &amp; greatest</title>' "${site}/docs/feed.xml" | cut -d: -f1)"
	created_line="$(grep -n -m1 '<title>Created date</title>' "${site}/docs/feed.xml" | cut -d: -f1)"
	[ "${latest_line}" -lt "${created_line}" ] || fail "expected RSS items to sort from newest to oldest"
	if php -r 'exit(class_exists("DOMDocument") ? 0 : 1);'
	then
		php -r '$xml = new DOMDocument(); exit($xml->load($argv[1]) ? 0 : 1);' "${site}/docs/feed.xml" \
			|| fail "expected generated RSS to be well-formed XML"
	fi
}

test_rss_validation_preserves_sources_and_output()
{
	begin_test "rss-validation"
	local site checksum
	site="$(make_temp_site)"
	CURRENT_SITE="${site}"
	(
		cd "${site}"
		"${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-first.log" 2>&1
	)

	cat > "${site}/.smgen.yaml" <<'EOF'
RSS_FEEDS:
  main:
    path: /feed.xml
    title: Main feed
    description: Recent pages
EOF
	cat > "${site}/pages/index.md" <<'EOF'
---
title: Replacement
feeds: [main]
---

# Replacement
EOF
	checksum="$(cksum "${site}/pages/index.md")"
	(
		cd "${site}"
		assert_command_fails "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-missing-date.log" 2>&1
	)
	assert_file_contains "${DEBUG_DIR}/${CURRENT_TEST}-missing-date.log" 'RSS page requires date or created'
	assert_file_contains "${site}/docs/index.html" '<h1 id="hello">Hello</h1>'
	assert_file_missing "${site}/docs/feed.xml"
	assert_file_excludes "${site}/pages/index.md" 'created:'
	[ "${checksum}" = "$(cksum "${site}/pages/index.md")" ] || fail "RSS validation changed the source page"

	cat > "${site}/pages/index.md" <<'EOF'
---
title: Replacement
date: 2026-10-24
feeds: [unknown]
---

# Replacement
EOF
	(
		cd "${site}"
		assert_command_fails "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-unknown.log" 2>&1
	)
	assert_file_contains "${DEBUG_DIR}/${CURRENT_TEST}-unknown.log" 'Unknown RSS feed unknown'
	assert_file_contains "${site}/docs/index.html" '<h1 id="hello">Hello</h1>'

	cat > "${site}/pages/index.md" <<'EOF'
---
title: Replacement
date: 2026-10-24
feeds:
  main:
---

# Replacement
EOF
	(
		cd "${site}"
		assert_command_fails "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-membership.log" 2>&1
	)
	assert_file_contains "${DEBUG_DIR}/${CURRENT_TEST}-membership.log" 'RSS feed membership must be true or false'
	assert_file_contains "${site}/docs/index.html" '<h1 id="hello">Hello</h1>'

	cat > "${site}/pages/index.md" <<'EOF'
---
title: Replacement
date: 2026-10-24
feeds: [main]
rss:
  media:content:
    _attributes:
      url: https://example.test/image.jpg
---

# Replacement
EOF
	(
		cd "${site}"
		assert_command_fails "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-namespace.log" 2>&1
	)
	assert_file_contains "${DEBUG_DIR}/${CURRENT_TEST}-namespace.log" 'Unknown RSS namespace prefix media'
	assert_file_contains "${site}/docs/index.html" '<h1 id="hello">Hello</h1>'

	cat > "${site}/pages/index.md" <<'EOF'
---
title: Replacement
date: 2026-10-24
feeds: [main]
rss:
  title: Custom replacement
---

# Replacement
EOF
	(
		cd "${site}"
		assert_command_fails "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-generated-field.log" 2>&1
	)
	assert_file_contains "${DEBUG_DIR}/${CURRENT_TEST}-generated-field.log" 'cannot replace generated element title'
	assert_file_contains "${site}/docs/index.html" '<h1 id="hello">Hello</h1>'

	cat > "${site}/.smgen.yaml" <<'EOF'
RSS_FEEDS:
  main:
    path: /sitemap.xml
    title: Main feed
    description: Recent pages
EOF
	cat > "${site}/pages/index.md" <<'EOF'
---
title: Replacement
date: 2026-10-24
feeds: [main]
---

# Replacement
EOF
	(
		cd "${site}"
		assert_command_fails "${SMGEN}" build > "${DEBUG_DIR}/${CURRENT_TEST}-collision.log" 2>&1
	)
	assert_file_contains "${DEBUG_DIR}/${CURRENT_TEST}-collision.log" 'RSS feed path collides with existing output'
	assert_file_contains "${site}/docs/index.html" '<h1 id="hello">Hello</h1>'
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

test_primary_mousedown_starts_navigation()
{
	begin_test "primary-mousedown-navigation"
	assert_file_contains "${ROOT_DIR}/static/main.js" "document.addEventListener('mousedown', event => {"
	assert_file_contains "${ROOT_DIR}/static/main.js" 'event.button !== 0'
	assert_file_contains "${ROOT_DIR}/static/main.js" 'event.ctrlKey || event.metaKey || event.shiftKey || event.altKey'
	assert_file_contains "${ROOT_DIR}/static/main.js" 'anchor.click();'
}

test_logo_preserves_its_aspect_ratio()
{
	begin_test "logo-aspect-ratio"
	assert_file_contains "${ROOT_DIR}/static/default.css" $'a.logo img.logo-image {\n\twidth: 6rem;\n\theight: auto;'
}

test_npm_package_installs_a_buildable_cli()
{
	begin_test "npm-package"
	local package_dir package_source archive_name archive consumer site fake_bin
	package_dir="$(mktemp -d "${TEMP_ROOT}/package.XXXXXX")"
	package_source="$(mktemp -d "${TEMP_ROOT}/package-source.XXXXXX")"
	node - "${ROOT_DIR}" "${package_source}" <<'NODE'
const fs = require('node:fs');
const path = require('node:path');

const [source, destination] = process.argv.slice(2);
const metadata = JSON.parse(fs.readFileSync(path.join(source, 'package.json'), 'utf8'));
for (const entry of ['README.md', 'LICENSE', ...metadata.files]) {
	if (path.isAbsolute(entry) || entry.split(/[\\/]/).includes('..')) {
		throw new Error(`Unsafe package entry: ${entry}`);
	}
	const target = path.join(destination, entry);
	fs.mkdirSync(path.dirname(target), {recursive: true});
	fs.cpSync(path.join(source, entry), target, {recursive: true});
}
delete metadata.scripts.prepack;
fs.writeFileSync(path.join(destination, 'package.json'), `${JSON.stringify(metadata, null, 2)}\n`);
NODE
	archive_name="$(cd "${package_source}" && npm_config_dry_run=false npm pack --ignore-scripts --pack-destination "${package_dir}" --silent)"
	archive="${package_dir}/${archive_name}"
	consumer="$(mktemp -d "${TEMP_ROOT}/consumer.XXXXXX")"
	printf '%s\n' '{"private":true}' > "${consumer}/package.json"
	(
		cd "${consumer}"
		npm_config_dry_run=false npm install --ignore-scripts --no-audit --no-fund --no-package-lock "${archive}" > "${DEBUG_DIR}/${CURRENT_TEST}-install.log" 2>&1
	)
	assert_file_exists "${consumer}/node_modules/smgen/helpers/bootstrap.php"
	assert_file_exists "${consumer}/node_modules/smgen/helpers/jsonld.php"
	assert_file_exists "${consumer}/node_modules/smgen/helpers/llms.php"
	assert_file_exists "${consumer}/node_modules/smgen/templates/page.php"
	assert_file_exists "${consumer}/node_modules/smgen/static/default.css"
	assert_file_exists "${consumer}/node_modules/smgen/.smgen-rc-default"
	"${consumer}/node_modules/.bin/smgen" help > "${DEBUG_DIR}/${CURRENT_TEST}-help.log"
	assert_file_contains "${DEBUG_DIR}/${CURRENT_TEST}-help.log" 'Usage: smgen'

	site="$(mktemp -d "${TEMP_ROOT}/site.XXXXXX")"
	CURRENT_SITE="${site}"
	fake_bin="${package_dir}/fake-bin"
	mkdir -p "${fake_bin}"
	cat > "${fake_bin}/curl" <<'EOF'
#!/usr/bin/env bash
while [ "$#" -gt 0 ]; do
	if [ "$1" = "-o" ]; then
		printf '%s\n' '---' 'title: Packed Site' '---' '' '# Packed Site' > "$2"
		exit 0
	fi
	shift
done
exit 1
EOF
	chmod +x "${fake_bin}/curl"
	(
		cd "${site}"
		PATH="${fake_bin}:${consumer}/node_modules/.bin:${PATH}" smgen init > "${DEBUG_DIR}/${CURRENT_TEST}-init.log" 2>&1
		PATH="${consumer}/node_modules/.bin:${PATH}" smgen build > "${DEBUG_DIR}/${CURRENT_TEST}-build.log" 2>&1
	)
	assert_file_contains "${site}/docs/index.html" 'Packed Site'
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
	test_jsonld_frontmatter
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
	test_llms_txt_uses_page_and_directory_frontmatter
	test_llms_validation_preserves_previous_output
	test_llms_heading_fallback_respects_fence_delimiters
	test_named_rss_feeds_use_page_flags_and_dates
	test_rss_validation_preserves_sources_and_output
	test_archive_navigation_builds_static_calendar_and_timeline
	test_archive_navigation_rejects_invalid_dates
	test_named_menus_plugins_and_timeline_backfill
	test_named_menu_validation_preserves_output
	test_external_links_use_noopener
	test_primary_mousedown_starts_navigation
	test_logo_preserves_its_aspect_ratio
	test_npm_package_installs_a_buildable_cli

	echo "PASS: all tests"
}

main "$@"
