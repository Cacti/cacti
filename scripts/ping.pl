#!/usr/bin/env perl

use strict;
use warnings;
use IPC::Open3;
use Symbol 'gensym';

delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/usr/local/sbin';

# take care for tcp:hostname or TCP:ip@
my $host = $ARGV[0] // '';
$host =~ s/^(?:tcp|udp)6?://gis;

# leave IPv6 in tact
my $host_copy = $host;
if (($host_copy =~ tr/://) == 0) {
	$host =~ s/:[0-9]{1,5}$//gis;
}

# Addition2
($host) = $host =~ /^([\w.:-]+)$/;

# always have the language in english
$ENV{LANG}='en_US.UTF-8';

#
# Get the OS name
#
my $osname = "$^O";

sub run_ping {
	my (@cmd) = @_;
	my $err = gensym;
	my $pid = open3(undef, my $out, $err, @cmd);
	my @lines = (<$out>, <$err>);
	waitpid($pid, 0);
	return join('', @lines);
}

my @ping_cmd;
if ($osname =~ /solaris/i) {
	# Solaris needs a different ping command
	@ping_cmd = ('ping', '-s', $host, '64', '1');
} else {
	@ping_cmd = ('ping', '-c', '1', $host);
}

my $output = run_ping(@ping_cmd);
my $ping = '';
for my $line (split /\n/, $output) {
	if ($line =~ /(icmp_[s|r]eq.*t(ime|emps)|unknown host|Unknown host|not supported|Name or service not known|No address associated with name)/) {
		$ping = $line;
		last;
	}
}
chomp($ping);

if (($ping =~ 'unknown host') || ($ping =~ 'Unknown host') || ($ping =~ 'not supported') || ($ping =~ 'Name or service not known') || ($ping =~ 'No address associated with name')) {
	if ((-f '/bin/ping6') || (-f '/sbin/ping6')) {
		my $output6 = run_ping('ping6', '-c', '1', $host);
		for my $line (split /\n/, $output6) {
			if ($line =~ /(icmp_[s|r]eq.*t(ime|emps))/) {
				$ping = $line;
				last;
			}
		}
		chomp($ping);
	}
}

if ($ping =~ m/(.*t(?:ime|emps)=)([\w\.]*).*(ms|usec)/) {
	my ($value, $unit) = ($2, $3);
	if ($value eq '') {
		print 'U'; 		# avoid cacti errors, but do not fake rrdtool stats
	} elsif ($unit eq 'usec') {
		print $value/1000;	# re-calculate in units of 'ms'
	} else {
		print $value;
	}
} else {
	print 'U';
}
