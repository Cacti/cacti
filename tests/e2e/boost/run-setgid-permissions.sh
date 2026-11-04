#!/bin/sh
set -eu

repo_path=${1:-/repo}
probe="$repo_path/tests/fixtures/Core/BoostSetgidIdentityProbe.php"
apply_probe="$repo_path/tests/fixtures/Core/BoostApplyRrdPermissionsProbe.php"
work_path=/tmp/cacti-boost-setgid

groupadd --gid 29100 rrdwriters
useradd --uid 29101 --gid rrdwriters --no-create-home rrdowner
useradd --uid 29102 --gid rrdwriters --no-create-home writerone
useradd --uid 29103 --gid rrdwriters --no-create-home writertwo

mkdir -p "$work_path/nonroot" "$work_path/root"
chown rrdowner:rrdwriters "$work_path/nonroot" "$work_path/root"
chmod 2770 "$work_path/nonroot" "$work_path/root"

runuser -u writerone -- php "$probe" \
	"$work_path/nonroot" \
	"$work_path/nonroot/host/graph" \
	"$work_path/nonroot/host/graph/sample.rrd"

test -g "$work_path/nonroot/host"
test -g "$work_path/nonroot/host/graph"
test "$(stat -c %G "$work_path/nonroot/host/graph")" = rrdwriters
test "$(stat -c %U "$work_path/nonroot/host/graph/sample.rrd")" = writerone
test "$(stat -c %G "$work_path/nonroot/host/graph/sample.rrd")" = rrdwriters
runuser -u writertwo -- sh -c "printf '%s' '-second-writer' >> '$work_path/nonroot/host/graph/sample.rrd'"

php "$probe" \
	"$work_path/root" \
	"$work_path/root/host/graph" \
	"$work_path/root/host/graph/sample.rrd"

test -g "$work_path/root/host"
test -g "$work_path/root/host/graph"
test "$(stat -c %U "$work_path/root/host/graph")" = rrdowner
test "$(stat -c %G "$work_path/root/host/graph")" = rrdwriters
test "$(stat -c %U "$work_path/root/host/graph/sample.rrd")" = rrdowner
test "$(stat -c %G "$work_path/root/host/graph/sample.rrd")" = rrdwriters
runuser -u writertwo -- sh -c "printf '%s' '-different-owner-writer' >> '$work_path/root/host/graph/sample.rrd'"

actual_rrd="$work_path/root/actual.rrd"
runuser -u rrdowner -- rrdtool create "$actual_rrd" \
	--start 1700000000 --step 60 \
	DS:value:GAUGE:120:U:U RRA:AVERAGE:0.5:1:10
chmod 0640 "$actual_rrd"

if runuser -u writertwo -- rrdtool update "$actual_rrd" 1700000060:1 2>/dev/null; then
	printf '%s\n' 'ERROR: different-owner RRDtool update unexpectedly succeeded without group write' >&2
	exit 1
fi

php "$apply_probe" "$actual_rrd"
runuser -u writertwo -- rrdtool update "$actual_rrd" 1700000060:1

printf '%s\n' 'PASS: setgid, chown/chgrp, restrictive umask, visible permission failure, and different-owner RRDtool writers'
