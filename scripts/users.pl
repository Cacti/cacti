#!/usr/bin/perl

if ($ARGV[0] eq "") {
	$inbound = `w | wc -l`;
	$inbound =~ s/ +//;
	$inbound = $inbound - 2;
}else{
	$inbound = `w | grep -c $ARGV[0]`;
}

chomp $inbound;
print $inbound;
