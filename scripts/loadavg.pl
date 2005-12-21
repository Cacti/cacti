#!/usr/bin/perl

#get load avg for 5;15;30 min
open(PROCESS,"uptime |");
$avg = <PROCESS>;
$avg =~ s/.*:\s*//;
close(PROCESS);

if ($ARGV[0] eq "5") {
	$avg = `echo "$avg" | awk '\{print \$1 \}'`;
}

if ($ARGV[0] eq "15") {
	$avg = `echo "$avg" | awk '\{print \$2 \}'`;
}

if ($ARGV[0] eq "30") {
	$avg = `echo "$avg" | awk '\{print \$3 \}'`;
}

chomp $avg;
$avg =~ s/,//;
$avg =~ s/\n//;
print $avg;
