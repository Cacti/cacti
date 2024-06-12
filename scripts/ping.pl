#!/usr/bin/env perl

delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/usr/local/sbin';

# take care for tcp:hostname or TCP:ip@
$host = $ARGV[0];
$host =~ s/(?:tcp|udp)6?:/$1/gis;

# leave IPv6 in tact
if (($host =~ tr/:://) == 0) {
  $host =~ s/:[0-9]{1,5}/$1/gis;
}

# Addition2
($host) = $host =~ /^([\w.:-]+)$/;

# always have the language in english
$ENV{LANG}='en_US.UTF-8';

#
# Get the OS name
#
my $osname = "$^O";

if ($osname =~ 'solaris') {
  # Solaris needs a different ping command
  $pingcmd="ping -s $host 64 1";
} else {
  $pingcmd="ping -c 1 $host";
}

# old linux version use 'icmp_seq'
# newer use 'icmp_req' instead
open(PROCESS, "$pingcmd 2>&1 | grep -E '(icmp_[s|r]eq.*t(ime|emps)|unknown host|Unknown host|not supported|Name or service not known|No address associated with name)' 2>/dev/null |");
$ping = <PROCESS>;
close(PROCESS);
chomp($ping);

if (($ping =~ 'unknown host') || ($ping =~ 'Unknown host') || ($ping =~ 'not supported') || ($ping =~ 'Name or service not known') || ($ping =~ 'No address associated with name')) {
	if ((-f '/bin/ping6') || (-f '/sbin/ping6')) {
		open(PROCESS, "ping6 -c 1 $host 2>&1 | grep 'icmp_[s|r]eq.*t(ime|emps)' 2>/dev/null |");
		$ping = <PROCESS>;
		close(PROCESS);
		chomp($ping);
	}
}

$ping =~ m/(.*t(?:ime|emps)=)(.*).*(ms|usec)/;

if ($2 == '') {
	print 'U'; 		# avoid cacti errors, but do not fake rrdtool stats
}elsif ($3 eq 'usec') {
	print $2/1000;	# re-calculate in units of 'ms'
}else{
	print $2;
}
