#!/usr/bin/perl

#gets inbound and outbound tcp connections
$conn = `snmpnetstat -c \"$ARGV[1]\" -n \"$ARGV[0]\" | grep -c \"$ARGV[2]\"`;

print $conn;
