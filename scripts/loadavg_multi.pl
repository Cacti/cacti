#!/usr/bin/perl

# get load avg for 1;5;10 min

$uptime = `uptime`;
$uptime =~ s/.*://;
$uptime =~ s/,//gi;
$uptime =~ s/^ //;

chomp $uptime;
print $uptime;