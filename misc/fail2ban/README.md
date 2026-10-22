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
Set Settings > Logging > Log Destination to `Logfile Only` or `Logfile and Syslog/Eventlog`,
and keep Generic Log Level above `NONE`, since that level stops all writes to the file.

Syslog cannot be used as the jail's source. Cacti forwards only `ERROR`, `WARNING`,
`STATS` and `NOTICE` messages to syslog, so the `AUTH FAILURE` lines never reach it.

## Log date format

Cacti stamps each log line with the format chosen under Settings > General >
Date Display Format and Date Separator. The filter recognises these without changes:

- year first, the default: `2026-10-22`, `2026/10/22`, `2026.10.22`
- year first with a month name: `2026-Oct-22`
- a month name in any other position: `Oct-22-2026`, `22-Oct-2026`

Each of these works with any of the three separators. The numeric month-first and day-first formats
(`10/22/2026` and `22/10/2026`) look the same to Fail2Ban, so the filter cannot tell
which one you use, and the line is stamped with the time Fail2Ban read it rather than
the time Cacti wrote it. If you use either, add `/etc/fail2ban/filter.d/cacti.local`
with the matching pattern:

```
[Definition]
# day first, e.g. 22/10/2026
datepattern = ^%%d[-/.]%%m[-/.]%%Y %%H:%%M:%%S
# or month first, e.g. 10/22/2026
#datepattern = ^%%m[-/.]%%d[-/.]%%Y %%H:%%M:%%S
```

## Reverse proxy caveat

The `ip` field is whatever `get_client_addr()` returns. Behind a reverse proxy that is
`REMOTE_ADDR` — i.e. the proxy's address — unless you configure Cacti to trust the proxy
and read the forwarded client address (see the proxy/`proxy_headers` settings in
`include/config.php`). Without that, Fail2Ban would ban the proxy. Configure trusted
proxies before enabling this jail in a proxied deployment.
