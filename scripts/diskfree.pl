#!/usr/bin/env perl

delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/usr/local/sbin';

#
# The argument names a device or mount point. It is passed to df as a single
# argument with no shell, and a value df could read as an option is refused.
#
my $path = defined $ARGV[0] ? $ARGV[0] : '';

if ($path !~ /^[\w.\/][\w.\/-]*\z/) {
	print 'megabytes:U percent:U';
	exit 1;
}

# POSIX df: -k with -P reports 1024-byte blocks on GNU, BSD, macOS and
# busybox alike, matching the GNU-only --block-size=1024 used before.
open(my $process, '-|', 'df', '-k', '-P', '--', $path) or exit 1;
foreach (<$process>) {
	next if /Filesystem/;

	if ($_ =~ /(\Q$path\E)(.* )(.*[0-9])(.* )(.*[0-9])(.* )(.*[0-9])(.* )(.*[0-9])%(.* )/) {
		print "megabytes:$7 percent:$9";
	}
}
close($process);
