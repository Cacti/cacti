#!/usr/bin/env bash
# Idempotent DB seed for the e2e harness. Run from the host; uses docker compose exec.
set -euo pipefail
IFS=$'\n\t'

cd "$(dirname "$0")"

DC=(docker compose -f docker-compose.yml)

# Wait for the DB to actually accept queries (compose healthcheck plus a real query).
echo "[setup] waiting for cacti-db readiness"
for _ in $(seq 1 60); do
    if "${DC[@]}" exec -T cacti-db mariadb -ucactiuser -pcactiuser -e 'SELECT 1' cacti >/dev/null 2>&1; then
        break
    fi
    sleep 2
done
"${DC[@]}" exec -T cacti-db mariadb -ucactiuser -pcactiuser -e 'SELECT 1' cacti >/dev/null

# Drop & recreate the schema so the CLI installer starts from a clean slate.
"${DC[@]}" exec -T cacti-db sh -c '
    mariadb -uroot -pcacti-e2e-root -e "DROP DATABASE IF EXISTS cacti; CREATE DATABASE cacti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON cacti.* TO \"cactiuser\"@\"%\";"
'

# cli/install_cacti.php is really an upgrade-and-finalize tool: it expects the
# baseline schema from cacti.sql to already exist before it runs the post-init
# settings (cacti_db_version, install_complete, default values). Pipe the SQL
# from inside the master container so the path is stable across hosts.
echo "[setup] importing baseline cacti.sql"
"${DC[@]}" exec -T cacti-master sh -c 'cat /var/www/html/cacti.sql' \
    | "${DC[@]}" exec -T cacti-db mariadb -ucactiuser -pcactiuser cacti

# Run Cacti's own CLI installer to populate the version rows, run upgrade
# scripts, and flip install_complete.
echo "[setup] running cli/install_cacti.php"
"${DC[@]}" exec -T cacti-master php /var/www/html/cli/install_cacti.php --accept-eula --install --mode=1

# Confirm the installer populated the version row; abort early if it did not.
# Cacti tracks the installed db version in a dedicated `version` table (see
# include/global.php: SELECT cacti FROM version LIMIT 1), not in the settings
# key/value store, so the check has to look there.
echo "[setup] verifying installer outcome"
ROW=$("${DC[@]}" exec -T cacti-db mariadb -ucactiuser -pcactiuser cacti -Nse "SELECT cacti FROM version LIMIT 1")
if [ -z "$ROW" ] || [ "$ROW" = "new_install" ]; then
    echo "[setup] FATAL: installer did not populate the version table (got '$ROW')" >&2
    exit 2
fi

# Seed admin password to a known value using bcrypt (compat_password_hash uses
# PASSWORD_DEFAULT which is bcrypt on PHP >=5.5). We compute the hash inside the
# master container via PHP so the algorithm matches whatever the running PHP build
# considers default.
echo "[setup] seeding admin password"
ADMIN_HASH=$("${DC[@]}" exec -T cacti-master php -r 'echo password_hash("cacti-e2e-admin", PASSWORD_DEFAULT);')
"${DC[@]}" exec -T cacti-db mariadb -ucactiuser -pcactiuser cacti -e "
    UPDATE user_auth SET password='${ADMIN_HASH}', must_change_password='', password_change='', enabled='on'
        WHERE username='admin';
"

# Register the secondary as a remote poller pointing at https://cacti-poller/.
# Idempotent: ON DUPLICATE KEY for id=2.
#
# Also flip allow_unsafe_httpd=on so the verify_peer_name / allow_self_signed
# guards introduced in commits e7c9918b4 + bf0a7a70e accept the poller's
# self-signed certificate. Without this seed, get_default_contextoption()
# leaves verify_peer_name=true and the probe fails with a verification error
# that the harness should NOT detect (the operator's intent is exactly
# "I trust my self-signed remote pollers").
echo "[setup] registering remote poller"
"${DC[@]}" exec -T cacti-db mariadb -ucactiuser -pcactiuser cacti -e "
    INSERT INTO poller (id, name, hostname, dbdefault, dbhost, dbuser, dbpass, dbport, status, disabled)
        VALUES (2, 'E2E Remote', 'cacti-poller', 'cacti', 'cacti-db', 'cactiuser', 'cactiuser', 3306, 1, '')
        ON DUPLICATE KEY UPDATE hostname=VALUES(hostname), name=VALUES(name), disabled='';
    INSERT INTO settings (name, value) VALUES ('allow_unsafe_httpd', 'on')
        ON DUPLICATE KEY UPDATE value=VALUES(value);
"

# Create a low-priv user with NO realm grants for test 04. Idempotent via ON DUPLICATE KEY.
echo "[setup] seeding low-priv user"
LOWPRIV_HASH=$("${DC[@]}" exec -T cacti-master php -r 'echo password_hash("cacti-e2e-lowpriv", PASSWORD_DEFAULT);')
"${DC[@]}" exec -T cacti-db mariadb -ucactiuser -pcactiuser cacti -e "
    INSERT INTO user_auth (id, username, password, realm, full_name, must_change_password, password_change, show_tree, show_list, show_preview, graph_settings, login_opts, policy_graphs, policy_trees, policy_hosts, policy_graph_templates, enabled, lastchange, lastlogin, password_history, locked, failed_attempts, lastfail, reset_perms)
        VALUES (1000, 'lowpriv', '${LOWPRIV_HASH}', 0, 'Low Privilege', '', '', 'on', 'on', 'on', 'on', 1, 2, 2, 2, 2, 'on', -1, -1, '-1', '', 0, 0, 0)
        ON DUPLICATE KEY UPDATE password=VALUES(password), enabled='on';
    DELETE FROM user_auth_realm WHERE user_id=1000;
    DELETE FROM user_auth_perms WHERE user_id=1000;
"

echo "[setup] complete"
