# Fail2Ban integration for Cacti

Cacti already locks a *user account* after repeated failures (Settings > Authentication,
`secpass_lockfailed`). Fail2Ban complements that by blocking the offending *IP address*
at the firewall, which stops distributed guessing and probing that never trips a single
account's lockout.

## How it works

On every failed authentication Cacti writes a fixed, machine-parseable line to its log
(tag `AUTH`), in addition to the human-readable messages:

```
AUTH FAILURE user="alice" realm="local" ip="203.0.113.7" reason="bad_password"
```

- `realm`  — `local`, `ldap`, or `domain`
- `reason` — `bad_password`, `no_such_user`, or `2fa`
- `ip`     — a validated address from Cacti's `get_client_addr()`

The bundled filter matches this line and extracts the IP.

## Install

1. Copy the filter:

   ```
   cp misc/fail2ban/cacti.conf /etc/fail2ban/filter.d/cacti.conf
   ```

2. Add the jail (edit `logpath` to your install), then reload:

   ```
   cp misc/fail2ban/jail.local /etc/fail2ban/jail.d/cacti.local
   # edit logpath, then:
   fail2ban-client reload
   ```

3. Verify the filter against your log:

   ```
   fail2ban-regex /var/www/html/cacti/log/cacti.log misc/fail2ban/cacti.conf
   ```

## Logging destination

The filter reads Cacti's file log (`path_cactilog`, default `<cacti>/log/cacti.log`).
If you route Cacti to syslog instead, point `logpath` at the syslog file that receives
the `AUTH` entries, or run a jail with `backend = systemd`.

## Reverse proxy caveat

The `ip` field is whatever `get_client_addr()` returns. Behind a reverse proxy that is
`REMOTE_ADDR` — i.e. the proxy's address — unless you configure Cacti to trust the proxy
and read the forwarded client address (see the proxy/`proxy_headers` settings in
`include/config.php`). Without that, Fail2Ban would ban the proxy. Configure trusted
proxies before enabling this jail in a proxied deployment.
