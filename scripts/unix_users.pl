#!/usr/bin/env perl

use strict;
use warnings;

delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/usr/local/sbin';

my $grep_string = $ARGV[0] // '';
($grep_string) = $grep_string =~ /^([\w]+)$/ if $grep_string ne '';

chomp $grep_string;

open(my $process, '-|', 'who') or exit 1;
my $count = 0;
while (my $line = <$process>) {
  chomp($line);
  next unless $line =~ /:/;
  if ($grep_string ne '') {
    next unless $line =~ /$grep_string/;
  }
  $count++;
}
close($process);

print $count;
