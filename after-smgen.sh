#!/usr/bin/env bash

set -euo pipefail

npx --no-install smgen-assistant build \
	--pages "${PAGES_DIR}" \
	--output "${OUTPUT_DIR}" \
	--base-url "${BASE_URL}"
