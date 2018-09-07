#!/usr/bin/perl

delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin';

open(PROCESS, "ps ax | grep -c : |");
$output = <PROCESS>;
close(PROCESS);
chomp($output);
print $output;
