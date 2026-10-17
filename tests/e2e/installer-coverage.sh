#!/usr/bin/env bash
# Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.
set -euo pipefail
export CACTI_TEST_COVERAGE=1
export CACTI_PR_RAW_COVERAGE=/tmp/cacti-pr-coverage
npm ci --ignore-scripts --no-audit --no-fund
npm run postinstall
npm --prefix tests/e2e ci --ignore-scripts --no-audit --no-fund
(cd tests/e2e && npx --no-install playwright install chromium)
bash tests/e2e/installer_resilience_docker.sh
