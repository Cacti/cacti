#!/usr/bin/perl

$output = `ps ax | grep -c :`;
chomp($output);
print $output;
