#!/usr/bin/perl

$mem = `cat /proc/meminfo | grep -w "$ARGV[0]"`;
$mem =~ s/($ARGV[0].*\s)(.*[0-9])( kB)//;

print $2;