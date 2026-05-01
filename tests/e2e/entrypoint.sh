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
CSP_MODE="${CACTI_CSP_MODE:-nonce-report}"

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

# 4. Stage CSP-harness plugins into the bind-mounted plugins/ tree. Plugin
#    sources are baked into /opt/cacti-plugins at image build time; the bind
#    mount of the repo root would otherwise hide them. Copy is idempotent:
#    re-running on an existing volume leaves the tree alone unless
#    CACTI_FORCE_PLUGINS=1 is set, which deletes and re-copies. This way a
#    developer editing thold or monitor source on the host doesn't get
#    silently overwritten on container restart.
FORCE_PLUGINS="${CACTI_FORCE_PLUGINS:-0}"
for plugin in thold monitor; do
    src="/opt/cacti-plugins/${plugin}"
    dst="${CACTI_ROOT}/plugins/${plugin}"
    if [ ! -d "${src}" ]; then
        log "skip ${plugin}: ${src} not present in image"
        continue
    fi
    if [ -d "${dst}" ] && [ "${FORCE_PLUGINS}" != "1" ]; then
        log "plugin ${plugin} already staged at ${dst}; leaving it alone"
        continue
    fi
    if [ -d "${dst}" ]; then
        log "CACTI_FORCE_PLUGINS=1: removing existing ${dst}"
        rm -rf "${dst}"
    fi
    log "staging ${plugin} from ${src} to ${dst}"
    mkdir -p "${dst}"
    cp -a "${src}/." "${dst}/"
done

# 5. Seed plugin_config rows so the plugin admin pages render. Status=1 is
#    "installed but not active" and status=5 is "active" in lib/plugins.php;
#    we use 1 because activating runs the plugin's install hook, which on
#    thold and monitor reaches into rrdtool / poller paths the harness does
#    not seed. Status=1 is enough for the plugin's index UI to render under
#    plugins.php and exercise its inline scripts, which is what the CSP
#    spec actually walks.
for plugin in thold monitor; do
    if [ ! -d "${CACTI_ROOT}/plugins/${plugin}" ]; then
        continue
    fi
    case "${plugin}" in
        thold)
            display="Threshold Engine"
            page="https://github.com/Cacti/plugin_thold"
            ;;
        monitor)
            display="Cacti Monitor Plugin"
            page="https://github.com/Cacti/plugin_monitor"
            ;;
    esac
    # plugin_config.directory is not a unique key in the stock schema, so
    # ON DUPLICATE KEY UPDATE would not actually deduplicate on re-run.
    # UPDATE first; INSERT only when no row already exists.
    mysql_cmd "UPDATE plugin_config
               SET name='${display}', status=1, author='Various', webpage='${page}', version='1.x'
               WHERE directory='${plugin}';"
    mysql_cmd "INSERT INTO plugin_config (directory, name, status, author, webpage, version)
               SELECT '${plugin}', '${display}', 1, 'Various', '${page}', '1.x'
               WHERE NOT EXISTS (
                   SELECT 1 FROM plugin_config WHERE directory='${plugin}'
               );"
    log "seeded plugin_config row for ${plugin} (status=1, installed)"
done

# 6. Make Cacti's writable directories world-writable. CI checks out the
#    repo as the runner user; the php-fpm container runs as www-data. The
#    bind mount preserves host UIDs, so without this Cacti dies on its
#    very first log line with "System log file is not available for
#    writing", killing emitHeaders() before it runs.
for d in log cache rra resource; do
    if [ -d "${CACTI_ROOT}/${d}" ]; then
        chmod -R a+w "${CACTI_ROOT}/${d}" 2>/dev/null || true
    fi
done
# Plugin dirs need to be readable by the php-fpm user; copy from the image
# preserves the build user's UID, which won't match www-data.
for plugin in thold monitor; do
    if [ -d "${CACTI_ROOT}/plugins/${plugin}" ]; then
        chmod -R a+rX "${CACTI_ROOT}/plugins/${plugin}" 2>/dev/null || true
    fi
done

# 7. Hand off to php-fpm.
log "exec: $*"
exec "$@"
