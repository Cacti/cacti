#!/usr/bin/env perl

delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/usr/local/sbin';

# read ps directly and leave out this script and its ps child; a
# 'ps ax | grep -c :' pipeline also counted its own sh, ps and grep
$ps_pid = open(PROCESS, '-|', 'ps', 'ax');
if (!$ps_pid) {
  print 'U';
  exit 1;
}

$output = 0;
while ($line = <PROCESS>) {
  next unless $line =~ /:/;
  next if ($line =~ /^\s*(\d+)\s/ && ($1 == $$ || $1 == $ps_pid));
  $output++;
}
close(PROCESS);
print $output;
