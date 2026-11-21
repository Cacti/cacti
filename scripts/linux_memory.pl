#!/usr/bin/env perl

delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/usr/local/sbin';

$val1 = defined $ARGV[0] ? $ARGV[0] : '';
($val1) = $val1 =~ /^([\w.:]+)$/;

# without a usable key grep has no pattern and nothing is reported
if (!defined $val1) {
  print 'U';
  exit 1;
}

open(PROCESS, "cat /proc/meminfo | grep -w $val1 |");
foreach (<PROCESS>) {
  if ($_ =~ /($ARGV[0].*\s)(.*[0-9])( kB)/) {
   print $2;
  }
}
close(PROCESS);
