#!/usr/bin/env perl

use strict;
use warnings;

delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/usr/local/sbin';


my $osname = "$^O";
my $path = $ARGV[0] // '';
($path) = $path =~ /^([\w\/-]+)$/ if $path ne '';

my @dfcmd;
if ($osname =~ /freebsd/i) {
	# FreeBSD have other parameters
	@dfcmd = ('df', '-k', '-P');
} else {
	@dfcmd = ('df', '--block-size=1024', '-P');
}

if ($path ne '') {
	push @dfcmd, $path;
}

open(my $process, '-|', @dfcmd) or exit 1;
while (my $line = <$process>) {
	chomp($line);
	next if $line =~ /^Filesystem\s+/;
	if ($line =~ /^(\S+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)%\s+(\S+)/) {
		print "megabytes:$4 percent:$5";
		if ($path ne '') {
			last;
		}
	}
}
close($process);