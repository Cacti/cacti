#!/usr/bin/perl

#gets inbound and outbound tcp connections
$conn = `netstat -n | grep -c \"$ARGV[0]\"`;

chomp $conn;
print $conn;