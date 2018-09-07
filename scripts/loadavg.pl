#!/usr/bin/env perl

delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/usr/local/sbin';

$val1 = $ARGV[0];
($val1) = $val1 =~ /^([\d]+)$/;

#get load avg for 5;15;30 min
open(PROCESS,"uptime |");
$avg = <PROCESS>;
$avg =~ s/.*:\s*//;
close(PROCESS);

if ($val1 eq "5") {
        $avg = `echo "$avg" | awk '\{print \$1 \}'`;
}

if ($val1 eq "15") {
        $avg = `echo "$avg" | awk '\{print \$2 \}'`;
}

if ($val1 eq "30") {
        $avg = `echo "$avg" | awk '\{print \$3 \}'`;
}

chomp $avg;
$avg =~ s/,//;
$avg =~ s/\n//;
print $avg;
