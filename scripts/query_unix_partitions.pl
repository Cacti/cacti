#!/usr/bin/perl

if (($ARGV[0] ne "query") && ($ARGV[0] ne "get") && ($ARGV[0] ne "index")) {
	print "usage:\n\n./query_unix_partitions.pl index\n./query_unix_partitions.pl query {device,mount,total,used,available,percent}\n./query_unix_partitions.pl get {device,mount,total,used,available,percent} DEVICE\n";
	exit;
}

open(DF, "/bin/df -P|");

while (<DF>) {
	#/dev/hda2             20157744  18553884    579860  97% /var
	if (/^(\/\S+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)%\s+(\/\S*)$/) {
		my %output = (
			device => $1,
			mount => $6,
			total => $2,
			used => $3,
			available => $4,
			percent => $5
		);

		if ($ARGV[0] eq "index") {
			print "$1\n";
		}elsif (($ARGV[0] eq "get") && ($ARGV[2] eq $1)) {
			print $output{$ARGV[1]};
		}elsif ($ARGV[0] eq "query") {
			print "$output{device}:$output{$ARGV[1]}\n";
		}
	}
}

close(DF);
