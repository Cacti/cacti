#!/usr/bin/env perl

delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/usr/local/sbin';

my $grep_string= $ARGV[0];
($grep_string) = $grep_string =~ /^([\w]+)$/;

chomp $grep_string;

if ($grep_string eq '') {
  open(PROCESS, "who | grep -c : |");
}else{
  open(PROCESS, "who | grep : | grep -c $grep_string |");
}
$output = <PROCESS>;
close(PROCESS);
chomp($output);
print $output;
