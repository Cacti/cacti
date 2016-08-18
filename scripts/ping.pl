#!/usr/bin/perl

# take care for tcp:hostname or TCP:ip@
$host = $ARGV[0];
$host =~ s/tcp:/$1/gis;

# leave IPv6 in tact
if (($host =~ tr/:://) == 0) {
  $host =~ s/:[0-9]{1,5}/$1/gis;
}

# always have the language in english
$ENV{LANG}='en_US.UTF-8';

# old linux version use 'icmp_seq'
# newer use 'icmp_req' instead
open(PROCESS, "ping -c 1 $host 2>&1 | grep -E '(icmp_[s|r]eq.*time|unknown host)' |");
$ping = <PROCESS>;
close(PROCESS);
chomp($ping);

if ($ping =~ 'unknown host') {
	if (-f '/bin/ping6') {
		open(PROCESS, "/bin/ping6 -c 1 $host 2>&1 | grep 'icmp_[s|r]eq.*time' |");
		$ping = <PROCESS>;
		close(PROCESS);
		chomp($ping);
	}
}

$ping =~ m/(.*time=)(.*) (ms|usec)/;

if ($2 == '') {
	print 'U'; 		# avoid cacti errors, but do not fake rrdtool stats
}elsif ($3 eq 'usec') {
	print $2/1000;	# re-calculate in units of 'ms'
}else{
	print $2;
}
