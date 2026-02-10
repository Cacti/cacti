#!/usr/bin/env perl

use strict;
use warnings;

delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/usr/local/sbin';

my $val1 = $ARGV[0];
($val1) = $val1 =~ /^([\w]+)$/;

my $val2 = $ARGV[1] // '';
($val2) = $val2 =~ /^([\w]+)$/ if $val2 ne '';

my $val3 = $ARGV[2] // '';
($val3) = $val3 =~ /^([\w\/-]+)$/ if $val3 ne '';

my %valid_fields = map { $_ => 1 } qw(device mount total used available percent);


if (($val1 ne "query") && ($val1 ne "get") && ($val1 ne "index") && ($val1 ne "num_indexes")) {
  print "usage:\n\n";
  print "./query_unix_partitions.pl index\n";
  print "./query_unix_partitions.pl num_indexes\n";
  print "./query_unix_partitions.pl query {device,mount,total,used,available,percent}\n";
  print "./query_unix_partitions.pl get {device,mount,total,used,available,percent} DEVICE\n";
  exit;
}

if (($val1 eq "query") || ($val1 eq "get")) {
  if (!$valid_fields{$val2}) {
    print "usage:\n\n";
    print "./query_unix_partitions.pl index\n";
    print "./query_unix_partitions.pl num_indexes\n";
    print "./query_unix_partitions.pl query {device,mount,total,used,available,percent}\n";
    print "./query_unix_partitions.pl get {device,mount,total,used,available,percent} DEVICE\n";
    exit;
  }
}

open(my $df_fh, '-|', '/bin/df', '-P', '-k', '-l') or exit;

my $count=0;
while (<$df_fh>) {
  #/dev/hda2             20157744  18553884    579860  97% /var
  if (/^(\/\S+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)%\s+(\/\S*)$/) {
   my %output = (
    device => $1,
    mount => $6,
    total => $2,
    used => $3,
    available => $4,
    percent => $5
   );

   if ($val1 eq "index") {
    print "$1\n";
   }elsif ($val1 eq "num_indexes") {
    $count++;
   }elsif (($val1 eq "get") && ($val3 eq $1)) {
    print $output{$val2};
   }elsif ($val1 eq "query") {
    print "$output{device}:$output{$val2}\n";
   }
  }
}

close($df_fh);

if ($val1 eq "num_indexes") {
  print "$count\n";
}
