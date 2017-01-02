#!/bin/sh
xgettext -k__gettext -k__ -k__n:1,2 -k__x:1c,2 -k__xn:1c,2,3 -k__date `find ../ -name \*.php` -o po/cacti.pot