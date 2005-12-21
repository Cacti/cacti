#!/usr/bin/perl
open(PROCESS,"df --block-size=1024 -P $ARGV[0] | grep -v Filesystem |");
foreach (<PROCESS>) {
	if ($_ =~ /($ARGV[0])(.* )(.*[0-9])(.* )(.*[0-9])(.* )(.*[0-9])(.* )(.*[0-9])%(.* )/) {
		print "megabytes:$7 percent:$9";
	}
}
close(PROCESS);
