#!/usr/bin/env perl

delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/usr/local/sbin';

#
# Get the OS name
#
my $osname = "$^O";

if ($osname =~ 'freebsd') {
  # FreeBSD have other parameters
  $dfcmd="df -k -P $ARGV[0] | grep -v Filesystem |";
} else {
  $dfcmd="df --block-size=1024 -P $ARGV[0] | grep -v Filesystem |";
}

open(PROCESS, $dfcmd);
foreach (<PROCESS>) {
	if ($_ =~ /($ARGV[0])(.* )(.*[0-9])(.* )(.*[0-9])(.* )(.*[0-9])(.* )(.*[0-9])%(.* )/) {
		print "megabytes:$7 percent:$9";
	}
}
close(PROCESS);
