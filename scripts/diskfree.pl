#!/usr/bin/perl

$ret = `df --block-size=1024 -P $ARGV[0] | grep -v Filesystem`;
$ret =~ s/($ARGV[0])(.* )(.*[0-9])(.* )(.*[0-9])(.* )(.*[0-9])(.* )(.*[0-9])%(.* )//;

print "megabytes:$7 percent:$9";
