#!/usr/bin/perl

if ($ARGV[0]=="x") {
	$db = ":0";
	$ARGV[0] = 2;
}

$response = `ping $ARGV[1] -c $ARGV[0] |grep round-trip| awk '\{print \$4 \}' | awk -F / '\{print \$1 \}' | grep -v "Warning"`;
chomp $response;
$response = $response;
print "$response$db";
