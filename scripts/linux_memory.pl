#!/usr/bin/env perl

delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/usr/local/sbin';

$val1 = $ARGV[0];
($val1) = $val1 =~ /^([\w.:]+)$/;

open(PROCESS, "cat /proc/meminfo | grep -w $val1 |");
foreach (<PROCESS>) {
  if ($_ =~ /($ARGV[0].*\s)(.*[0-9])( kB)/) {
   print $2;
  }
}
close(PROCESS);
