#!/bin/sh
# Stands in for snmpget and snmpbulkwalk in the disk counter script tests.
# The OID is the last argument; the answers mimic a device whose third disk
# returns a value that is not a counter.

for oid; do :; done

case "$oid" in
	.1.3.6.1.2.1.1.3.0)
		echo '500'
		;;
	.1.3.6.1.4.1.2021.13.15.1.1.2)
		echo "$oid.1 = sda"
		echo "$oid.2 = sda1"
		echo "$oid.3 = sdb"
		;;
	.1.3.6.1.4.1.2021.13.15.1.1.5|.1.3.6.1.4.1.2021.13.15.1.1.12)
		echo "$oid.1 = 1500"
		echo "$oid.3 = not-a-counter"
		;;
	.1.3.6.1.4.1.2021.13.15.1.1.6|.1.3.6.1.4.1.2021.13.15.1.1.13)
		echo "$oid.1 = 2000"
		echo "$oid.3 = 3000"
		;;
esac

exit 0
