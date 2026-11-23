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

if ($grep_string eq '') {
  open(PROCESS, "who | grep -c : |");
}else{
  open(PROCESS, "who | grep : | grep -c $grep_string |");
}
$output = <PROCESS>;
close(PROCESS);
chomp($output);
print $output;
