#!/usr/bin/perl

my $grep_string = $ARGV[0];

chomp $grep_string;

if ($grep_string eq '') {
        $output = `who | grep -c :`;
}else{
        $output = `who | grep : | grep -c $grep_string`;
}

chomp($output);
print $output;
