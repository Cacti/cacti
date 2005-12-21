#!/usr/bin/perl

my $grep_string = $ARGV[0];

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
