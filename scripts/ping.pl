#!/usr/bin/perl

$ping = `/bin/ping -c $ARGV[0] -w 300 -n -q $ARGV[1]`;
($packet_loss) = ($ping =~ /received, (\d+)% loss/);
($round_trip) = ($ping =~ /rtt min\/avg\/max\/mdev = \d+\.\d+\/(\d+\.\d+)\/.* ms/);

print "$round_trip $packet_loss"; 
