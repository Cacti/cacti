#!/usr/bin/env perl
use strict;
use warnings;


delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/usr/local/sbin';

# some locales print the averages with a decimal comma
$ENV{LC_ALL} = 'C';

my $val1 = $ARGV[0] // '';
($val1) = $val1 =~ /^([\d]+)$/ if $val1 ne '';

#get load avg for 5;15;30 min
open(PROCESS, '-|', 'uptime');
my $avg = <PROCESS>;
$avg =~ s/.*:\s*//;
close(PROCESS);

if (($val1 eq "5") || ($val1 eq "15") || ($val1 eq "30")) {
        # Linux separates the averages with commas, BSD and macOS with spaces
        my @parts = split(/[\s,]+/, $avg);
        if ($val1 eq "5") {
                $avg = $parts[0];
        } elsif ($val1 eq "15") {
                $avg = $parts[1];
        } elsif ($val1 eq "30") {
                $avg = $parts[2];
        }
}

chomp $avg;
$avg =~ s/,//;
$avg =~ s/\n//;
print $avg;
