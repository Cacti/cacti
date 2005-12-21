#!/usr/bin/perl

open(PROCESS, "/bin/bash -c 'wget --quiet -O - http://192.168.100.1/cgibin/opcfg | grep \"10.18.18.11\" -c' |");
$status = <PROCESS>;
close(PROCESS);
chomp $status;
print $status;
