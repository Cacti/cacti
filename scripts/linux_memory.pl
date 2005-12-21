#!/usr/bin/perl

open(PROCESS, "cat /proc/meminfo | grep -w $ARGV[0] |");
foreach (<PROCESS>) {
	if ($_ =~ /($ARGV[0].*\s)(.*[0-9])( kB)/) {
		print $2;
	}
}
close(PROCESS);
