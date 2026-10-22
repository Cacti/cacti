#!/usr/bin/env perl

use strict;
use warnings;

delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/usr/local/sbin';

my $grep_string = $ARGV[0] // '';
if ($grep_string ne '') {
  # An argument that fails validation must not fall through to an unfiltered
  # count, which would graph every session as if it belonged to this user.
  ($grep_string) = $grep_string =~ /^([\w.-]+)$/;
  if (!defined $grep_string) {
    print 'U';
    exit 1;
  }
}

open(my $process, '-|', 'who') or exit 1;
my $count = 0;
while (my $line = <$process>) {
  chomp($line);
  next unless $line =~ /:/;
  if ($grep_string ne '') {
    next unless $line =~ /\Q$grep_string\E/;
  }
  $count++;
}
close($process);

print $count;
