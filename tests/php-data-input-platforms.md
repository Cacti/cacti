# PHP data input script platforms

These scripts are optional ports of the existing Perl and Python inputs.
They do not change an installed data input method or replace its command.
Configure a PHP script only on a collector that provides its data source.

| Scripts | Collector requirements |
| --- | --- |
| `ss_linux_memory.php` | Linux `/proc/meminfo` |
| `ss_loadavg.php`, `ss_loadavg_multi.php` | `/proc/loadavg` or PHP's `sys_getloadavg()` |
| `ss_diskfree.php`, `ss_query_unix_partitions.php` | Unix `df` |
| `ss_unix_processes.php` | Linux `/proc` or Unix `ps` |
| `ss_unix_users.php` | Unix `who` |
| `ss_unix_tcp_connections.php` | Unix `netstat` or `ss` |
| `ss_ping.php` | Unix `ping` or `ping6`; Windows ping flags and output are not supported |
| `ss_webhits.php` | A readable access log in the expected format |
| `ss_3com_cable_modem.php`, `ss_motorola_surfboard_SB6141.php`, `ss_weatherbug.php` | Reachable device HTTP endpoint and Cacti's `cacti_http()` |

The Unix collectors do not provide native Windows counters. The process
helper returns unavailable on Windows rather than launching Unix commands
or using Unix pipe selection. Load averages return `U`, or
`1min:U 5min:U 10min:U`, when both load-average facilities are absent; they
do not terminate the Script Server. Use Windows-specific data input methods
for those counters.

The Windows PHP data input scripts workflow runs on native Windows Server
2022 with PHP 8.2, 8.3 and 8.4. It checks log reads, modem HTTP fixtures,
response parsers, silent Script Server loading, and repeated unavailable
Unix collector calls followed by a successful log read. Skipped tests fail
the Windows job. This checks these scripts, not a complete Windows Cacti
installation or native Windows performance counters.

Linux Docker tests cover the parsers, HTTP fixtures, subprocess behavior
and Script Server lifecycle. A separate subprocess regression disables
`sys_getloadavg()` and denies access to `/proc` to verify the unavailable-data
path without a Windows runtime.
