#!/usr/bin/perl

my $grep_string = $ARGV[0];

chomp $grep_string;

if ($grep_string eq '') {
        print `who | grep -c :`;
}else{
        print `who | grep : | grep -c $grep_string`;
}

