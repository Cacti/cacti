#!/usr/bin/perl

my $grep_string = $ARGV[0];

chomp $grep_string;

if ($grep_string eq '') {
	open(PROCESS, "netstat -n | grep -c tcp | ");
}else{
	open(PROCESS, "netstat -n | grep tcp | grep -c $grep_string |");
}
$output = <PROCESS>;
close(PROCESS);
chomp($output);
print $output;
