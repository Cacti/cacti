#!/usr/bin/perl

my $grep_string = $ARGV[0];

chomp $grep_string;

if ($grep_string eq '') {
	$output = `netstat -n | grep -c tcp`;
}else{
	$output = `netstat -n | grep tcp | grep -c $grep_string`;
}

chomp($output);
print $output;
