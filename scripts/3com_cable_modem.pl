#!/usr/bin/perl

$status=`/bin/bash -c 'wget --quiet -O - http://192.168.100.1/cgibin/opcfg | grep "10.18.18.11" -c'`;
chomp $status;
print $status;
