#!/usr/bin/perl

#gets inbound and outbound tcp connections

if ($ARGV[0] eq "tcp") {
	$conn = `netstat -n | grep -c \":\"`;
	chomp $conn;
}else{
	$conn = `netstat -na | grep -c LISTEN`;
}

$conn =~ s/ //;

print $conn;