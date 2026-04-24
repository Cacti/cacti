#!/usr/bin/env bash
# Cacti E2E entrypoint. Bootstraps include/config.php, waits for MariaDB,
# marks the schema as installed (cacti.sql ships with version='new_install'
# which otherwise triggers the web wizard), flips CSP to nonce mode, and
# relaxes the default-admin password-change flag so Playwright can log in.
#
# Idempotent: safe to re-run on a container restart against an existing
# volume. Never truncates tables.

set -euo pipefail

CACTI_ROOT=/var/www/html/cacti
DB_HOST="${CACTI_DB_HOST:-mariadb}"
DB_PORT="${CACTI_DB_PORT:-3306}"
DB_NAME="${CACTI_DB_NAME:-cacti}"
DB_USER="${CACTI_DB_USER:-cactiuser}"
DB_PASS="${CACTI_DB_PASS:-cactipass}"
CSP_MODE="${CACTI_CSP_MODE:-nonce}"

log() { printf '[entrypoint] %s\n' "$*" >&2; }

# 1. Seed include/config.php from the .dist template. CACTI_FORCE_CONFIG=1
#    overwrites an existing file so dev re-runs and CI always start with
#    DB creds matching this compose stack. Developer installs with the
#    default empty value keep their edited config across restarts.
config_php="${CACTI_ROOT}/include/config.php"
FORCE_CONFIG="${CACTI_FORCE_CONFIG:-0}"
if [ ! -f "${config_php}" ] || [ "${FORCE_CONFIG}" = "1" ]; then
    if [ -f "${config_php}" ]; then
        log "overwriting include/config.php (CACTI_FORCE_CONFIG=1)"
    else
        log "creating include/config.php from include/config.php.dist"
    fi
    cp "${CACTI_ROOT}/include/config.php.dist" "${config_php}"
    # Rewrite rules tolerate varying whitespace around '=' so they work
    # against both the .dist (aligned) and hand-edited (single-space)
    # variants of the template.
    sed -i -E \
        -e "s|^(\\\$database_hostname[[:space:]]*=[[:space:]]*)'[^']*';|\\1'${DB_HOST}';|" \
        -e "s|^(\\\$database_username[[:space:]]*=[[:space:]]*)'[^']*';|\\1'${DB_USER}';|" \
        -e "s|^(\\\$database_password[[:space:]]*=[[:space:]]*)'[^']*';|\\1'${DB_PASS}';|" \
        -e "s|^(\\\$database_default[[:space:]]*=[[:space:]]*)'[^']*';|\\1'${DB_NAME}';|" \
        -e "s|^(\\\$database_port[[:space:]]*=[[:space:]]*)'[^']*';|\\1'${DB_PORT}';|" \
        -e "s|^(\\\$url_path[[:space:]]*=[[:space:]]*)'[^']*';|\\1'/';|" \
        "${config_php}"
else
    log "include/config.php present; CACTI_FORCE_CONFIG unset. leaving it alone"
fi

# 2. Wait for MariaDB using the PHP mysqli client (same stack Cacti uses at
#    runtime). Retry for up to ~60s.
log "waiting for MariaDB at ${DB_HOST}:${DB_PORT}"
attempt=0
until php -r "
\$m = @new mysqli('${DB_HOST}', '${DB_USER}', '${DB_PASS}', '${DB_NAME}', ${DB_PORT});
if (\$m->connect_errno) { exit(1); }
exit(0);
" 2>/dev/null; do
    attempt=$((attempt + 1))
    if [ "${attempt}" -ge 30 ]; then
        log "MariaDB did not become reachable after ${attempt} attempts; aborting"
        exit 1
    fi
    sleep 2
done
log "MariaDB reachable after ${attempt} attempt(s)"

# 3. Post-seed SQL. cacti.sql is loaded by the MariaDB init hook at first
#    boot, so the schema is already present. These statements are guarded so
#    they are safe on re-run.
#
#    - version='new_install' is Cacti's "run the web wizard" sentinel. For
#      E2E we mark installation complete by writing the distribution version
#      string. The integration fixture uses the same trick.
#    - settings row for content_security_policy_script is upserted so the
#      pilot pages emit nonce mode.
#    - user_auth.admin gets must_change_password cleared so logging in as
#      admin/admin does not redirect into the force-change page.

if [ -f "${CACTI_ROOT}/include/cacti_version" ]; then
    CACTI_VER=$(tr -d '[:space:]' < "${CACTI_ROOT}/include/cacti_version")
else
    # Older 1.2 trees shipped the version in a PHP constant file.
    CACTI_VER=$(grep -E "^define\\('CACTI_VERSION'" "${CACTI_ROOT}/include/cacti_version.php" 2>/dev/null \
        | sed -E "s/.*'([^']+)'\\).*/\\1/" || true)
fi
if [ -z "${CACTI_VER}" ]; then
    CACTI_VER="1.2.0"
fi
log "marking Cacti version as ${CACTI_VER} (was new_install)"

mysql_cmd() {
    mariadb \
        --host="${DB_HOST}" \
        --port="${DB_PORT}" \
        --user="${DB_USER}" \
        --password="${DB_PASS}" \
        --protocol=TCP \
        --batch --silent \
        "${DB_NAME}" \
        --execute="$1"
}

mysql_cmd "UPDATE version SET cacti='${CACTI_VER}' WHERE cacti='new_install';"
mysql_cmd "INSERT INTO settings (name, value) VALUES ('install_complete','1')
           ON DUPLICATE KEY UPDATE value=VALUES(value);"
mysql_cmd "INSERT INTO settings (name, value) VALUES ('content_security_policy_script','${CSP_MODE}')
           ON DUPLICATE KEY UPDATE value=VALUES(value);"
# Default admin ships with must_change_password='on'. Clear it so tests can
# authenticate as admin/admin without being bounced to the change-password
# flow on first login.
mysql_cmd "UPDATE user_auth SET must_change_password='', password_change='', lastchange=UNIX_TIMESTAMP()
           WHERE username='admin';"

log "post-seed SQL complete; CSP mode=${CSP_MODE}"

# 4. Hand off to php-fpm.
log "exec: $*"
exec "$@"
