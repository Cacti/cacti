#!/usr/bin/perl

# take care for tcp:hostname or TCP:ip@
$host = $ARGV[0];
$host =~ s/tcp:/$1/gis;
$host =~ s/:[0-9]{1,5}/$1/gis;

# old linux version use "icmp_seq"
# newer use "icmp_req" instead
open(PROCESS, "ping -c 1 $host | grep 'icmp_[s|r]eq' | grep time |");
$ping = <PROCESS>;
close(PROCESS);
$ping =~ m/(.*time=)(.*) (ms|usec)/;

if ($2 == "") {
	print "U"; 		# avoid cacti errors, but do not fake rrdtool stats
}elsif ($3 eq "usec") {
	print $2/1000;	# re-calculate in units of "ms"
}else{
	print $2;
}
