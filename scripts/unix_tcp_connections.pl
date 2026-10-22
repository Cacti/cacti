#!/usr/bin/env perl

use strict;
use warnings;

delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/usr/local/sbin';

# ss abbreviates or hyphenates the state names that netstat prints. Graphs
# and existing grepstr values were built against netstat, so ss output is
# rewritten to the netstat spelling before filtering.
my %ss_state = (
  'ESTAB'      => 'ESTABLISHED',
  'SYN-SENT'   => 'SYN_SENT',
  'SYN-RECV'   => 'SYN_RECV',
  'FIN-WAIT-1' => 'FIN_WAIT1',
  'FIN-WAIT-2' => 'FIN_WAIT2',
  'TIME-WAIT'  => 'TIME_WAIT',
  'CLOSE-WAIT' => 'CLOSE_WAIT',
  'LAST-ACK'   => 'LAST_ACK',
);

my $grep_string = $ARGV[0] // '';
if ($grep_string ne '') {
  # An argument that fails validation must not fall through to an unfiltered
  # count of every connection.
  ($grep_string) = $grep_string =~ /^([\w.:-]+)$/;
  if (!defined $grep_string) {
    print 'U';
    exit 1;
  }

  $grep_string = $ss_state{$grep_string} if exists $ss_state{$grep_string};
}

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
    # ss without -a also hides TIME-WAIT and SYN-RECV, which netstat -n
    # reports, so list everything and drop the listeners below instead.
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
  } else {
    next if $line =~ /^(?:State|LISTEN)\s+/;
    $line =~ s/^(\S+)/exists $ss_state{$1} ? $ss_state{$1} : $1/e;
  }

  if ($grep_string ne '') {
    next unless $line =~ /\Q$grep_string\E/;
  }
  $count++;
}
close($process);

print $count;
