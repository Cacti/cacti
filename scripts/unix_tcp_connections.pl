#!/usr/bin/env perl

use strict;
use warnings;

delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/usr/local/sbin';

my $grep_string = $ARGV[0] // '';
($grep_string) = $grep_string =~ /^([\w]+)$/ if $grep_string ne '';

chomp $grep_string;

sub find_cmd {
  my @candidates = @_;
  for my $cmd (@candidates) {
    return $cmd if -x $cmd;
  }

  return undef;
}

my @cmd;
my $mode = '';
my $netstat = find_cmd('/bin/netstat', '/usr/bin/netstat', '/usr/sbin/netstat');
if (defined $netstat) {
  @cmd = ($netstat, '-n');
  $mode = 'netstat';
} else {
  my $ss = find_cmd('/bin/ss', '/usr/bin/ss', '/usr/sbin/ss');
  if (defined $ss) {
    @cmd = ($ss, '-tan');
    $mode = 'ss';
  } else {
    print 'U';
    exit 1;
  }
}

open(my $process, '-|', @cmd) or exit 1;
my $count = 0;
while (my $line = <$process>) {
  chomp($line);

  if ($mode eq 'netstat') {
    next unless $line =~ /tcp/;
    if ($grep_string ne '') {
      next unless $line =~ /$grep_string/;
    }
    $count++;
  } else {
    next if $line =~ /^State\s+/;
    if ($grep_string ne '') {
      next unless $line =~ /$grep_string/;
    }
    $count++;
  }
}
close($process);

print $count;
