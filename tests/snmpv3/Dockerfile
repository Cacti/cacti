FROM debian:bookworm

ENV DEBIAN_FRONTEND=noninteractive

RUN printf '#!/bin/sh\nexit 101\n' > /usr/sbin/policy-rc.d \
	&& chmod +x /usr/sbin/policy-rc.d \
	&& apt-get update \
	&& apt-get install -y --no-install-recommends \
		libcap2 \
		libmariadb3 \
		libsnmp40 \
	&& mkdir -p /etc/spine \
	&& apt-get download cacti-spine \
	&& dpkg-deb -x cacti-spine_*.deb / \
	&& rm -f cacti-spine_*.deb \
	&& ln -sf /usr/sbin/spine /usr/local/bin/spine \
	&& apt-get clean \
	&& rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html/cacti

ENTRYPOINT ["spine"]
