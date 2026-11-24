#!/usr/bin/env perl

delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/usr/local/sbin';

my $grep_string = defined $ARGV[0] ? $ARGV[0] : '';

# a refused user name must not fall through to the count of all users
if ($grep_string ne '') {
  ($grep_string) = $grep_string =~ /^([\w]+)$/;

  if (!defined $grep_string) {
    print 'U';
    exit 1;
  }
}

if (!open(PROCESS, '-|', 'who')) {
  print 'U';
  exit 1;
}

# a user name matches the first column only, not a tty or host that
# contains it
$output = 0;
while ($line = <PROCESS>) {
  next unless $line =~ /:/;
  next if ($grep_string ne '' && (split(' ', $line))[0] ne $grep_string);
  $output++;
}
close(PROCESS);
print $output;
