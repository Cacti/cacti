#!/usr/bin/env perl

use strict;
use warnings;

delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/usr/local/sbin';

# Read ps directly and skip this script and its ps child, which a
# 'ps ax | grep -c :' pipeline counted along with its own sh and grep.
my $ps_pid = open(my $process, '-|', 'ps', 'ax') or exit 1;
my $count = 0;
while (my $line = <$process>) {
  next unless $line =~ /:/;
  if ($line =~ /^\s*(\d+)\s/) {
    next if $1 == $$ || $1 == $ps_pid;
  }
  $count++;
}
close($process);

print $count;
