#!/usr/bin/env perl

use strict;
use warnings;

delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/usr/local/sbin';

my $key = $ARGV[0] // '';
($key) = $key =~ /^([\w().]+):?$/;
if (!defined $key) {
  print 'U';
  exit 1;
}

# Match the whole key so that 'Active' does not also pick up Active(anon)
# and Active(file) and print their values run together.
open(my $meminfo, '<', '/proc/meminfo') or do { print 'U'; exit 1; };
my $value = 'U';
while (my $line = <$meminfo>) {
  if ($line =~ /^\Q$key\E:\s+(\d+) kB/) {
    $value = $1;
    last;
  }
}
close($meminfo);

print $value;
