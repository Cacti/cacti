#!/bin/sh
df --block-size=1024 -P $1 | perl -ape '$F[4]=~tr/%//d;}{print "megabytes:$F[3] percent:$F[4]"';
