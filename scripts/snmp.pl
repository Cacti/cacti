#!/usr/bin/perl

$ret = `snmpget -O neEXbqfsStv $ARGV[0] $ARGV[1] $ARGV[2]`;
chomp $ret;

print $ret;