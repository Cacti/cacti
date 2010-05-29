#!/usr/bin/perl

if (($ARGV[0] ne "query") && ($ARGV[0] ne "get") && ($ARGV[0] ne "index") && ($ARGV[0] ne "num_indexes")) {
	print "usage:\n\n";
	print "./query_unix_partitions.pl index\n";
	print "./query_unix_partitions.pl num_indexes\n";
	print "./query_unix_partitions.pl query {device,mount,total,used,available,percent}\n";
	print "./query_unix_partitions.pl get {device,mount,total,used,available,percent} DEVICE\n";
	exit;
}

open(DF, "/bin/df -P -k|");

$count=0;
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
		}elsif ($ARGV[0] eq "num_indexes") {
			$count++;
		}elsif (($ARGV[0] eq "get") && ($ARGV[2] eq $1)) {
			print $output{$ARGV[1]};
		}elsif ($ARGV[0] eq "query") {
			print "$output{device}:$output{$ARGV[1]}\n";
		}
	}
}

close(DF);

if ($ARGV[0] eq "num_indexes") {
	print "$count\n";
}
