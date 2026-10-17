#!/usr/bin/env bash
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
# Explicit source-checkout preparation; never called by the web installer.
set -euo pipefail
cd "$(dirname "$0")/.."
composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction --no-progress
composer check-platform-reqs --no-dev
npm ci --ignore-scripts --no-audit --no-fund
npm run postinstall
if ! php install/preflight.php; then
  # Installed metadata can survive an interrupted copy with missing package files.
  composer reinstall --optimize-autoloader --prefer-dist --no-interaction --no-progress '*'
  php install/preflight.php
fi
printf 'Dependencies and frontend assets are ready. Back up the installation before upgrading.\n'
