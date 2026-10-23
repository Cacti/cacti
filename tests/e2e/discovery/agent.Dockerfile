# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
FROM debian:bookworm-slim
RUN apt-get update && apt-get install -y --no-install-recommends snmpd && rm -rf /var/lib/apt/lists/*
CMD ["snmpd", "-f", "-Lo", "-C", "-c", "/etc/snmp/snmpd.conf"]
