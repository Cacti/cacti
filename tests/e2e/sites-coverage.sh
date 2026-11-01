#!/usr/bin/env bash
# Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.
set -euo pipefail
: "${CACTI_PR_COVERAGE_OUTPUT:?Coverage needs an evidence directory}"
npm ci --prefix tests/e2e --ignore-scripts
(cd tests/e2e && npx --no-install playwright install --with-deps chromium)
CACTI_E2E_XDEBUG_EXTENSION="${CACTI_PR_XDEBUG_EXTENSION:-}" python3 tests/e2e/definition_resolution.py --kind sites --output "$CACTI_PR_COVERAGE_OUTPUT/sites-browser"
cp "$CACTI_PR_COVERAGE_OUTPUT"/sites-browser/php/*.json "$CACTI_PR_COVERAGE_OUTPUT/"
