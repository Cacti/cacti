#!/usr/bin/perl

$ret = `df --block-size=1024 $ARGV[0] | grep -v Filesystem`;
$ret =~ s/($ARGV[0])(.* )(.*[0-9])(.* )(.*[0-9])(.* )(.*[0-9])(.* )(.*[0-9]%)(.* )//;

print "$7:$9";