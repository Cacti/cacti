#!/usr/bin/perl 

$ping = `ping -c 1 $ARGV[0] | grep icmp_seq`; 
$ping =~ m/(.*time=)(.*) (ms|usec)/; 

print $2; 
