#!/usr/bin/env perl

use strict;
use warnings;

delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/usr/local/sbin';


my $path = $ARGV[0] // '';
if ($path ne '') {
	# A path that fails validation must not fall through to listing every
	# filesystem. A leading '-' would also be read by df as an option.
	($path) = $path =~ /^([\w.\/][\w.\/-]*)$/;
	if (!defined $path) {
		print 'megabytes:U percent:U';
		exit 1;
	}
}

# POSIX df: -k with -P reports 1024-byte blocks on GNU, BSD, macOS and
# busybox alike, matching the GNU-only --block-size=1024 used before.
my @dfcmd = ('df', '-k', '-P');

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
