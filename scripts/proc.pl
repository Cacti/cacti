#!/usr/bin/perl

$outbound = `ps ax | wc -l`;
$outbound =~ s/ +//;

chop($outbound);

$outbound--;

print("$outbound\n");