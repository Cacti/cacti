#!/usr/bin/perl

my $grep_string = $ARGV[0];

chomp $grep_string;

if ($grep_string eq '') {
	print `netstat -n | grep -c tcp`;
}else{
	print `netstat -n | grep tcp | grep -c $grep_string`;
}
