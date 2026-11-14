#!/usr/bin/env bash
# +-------------------------------------------------------------------------+
# | Copyright (C) 2004-2026 The Cacti Group                                 |
# |                                                                         |
# | This program is free software; you can redistribute it and/or           |
# | modify it under the terms of the GNU General Public License             |
# | as published by the Free Software Foundation; either version 2          |
# | of the License, or (at your option) any later version.                  |
# |                                                                         |
# | This program is distributed in the hope that it will be useful,         |
# | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
# | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
# | GNU General Public License for more details.                            |
# +-------------------------------------------------------------------------+
# | Cacti: The Complete RRDtool-based Graphing Solution                     |
# +-------------------------------------------------------------------------+
# | This code is designed, written, and maintained by the Cacti Group. See  |
# | about.php and/or the AUTHORS file for specific developer information.   |
# +-------------------------------------------------------------------------+
# | http://www.cacti.net/                                                   |
# +-------------------------------------------------------------------------+
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
