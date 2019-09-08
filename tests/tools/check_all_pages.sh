#!/bin/bash
# This script is supposed to test cacti a little bit. At least each page and
# each link is tried. I mean to add checks for new CVE's (at least those that I
# can trigger with wget) as well.

# ------------------------------------------------------------------------------
# Debugging
# ------------------------------------------------------------------------------
# set -xv

#exec 2> /dev/null

started=0

# ------------------------------------------------------------------------------
# OS Specific Paths
# ------------------------------------------------------------------------------
if [ -f /etc/redhat-release ]; then
	CACTI_LOG="/var/www/html/cacti/log/cacti.log"
	CACTI_ERRLOG="/var/www/html/cacti/log/cacti.stderr.log"
	APACHE_ERROR="/var/log/httpd/error_log"
	APACHE_ACCESS="/var/log/httpd/access_log"
	LIGHT_ERROR="/var/log/lighttpd/error_log"
	RRA_ARCHIVE="/var/www/html/cacti/rra/archive/"
	RRA_SHELL="/var/www/html/cacti/rra/shell.php"
	POLLER="/var/www/html/cacti/poller.php"
	WEBUSER="apache"
elif [ -f /etc/debian_version ]; then
	CACTI_LOG="/var/log/cacti/cacti.log"
	CACTI_ERRLOG="/var/log/cacti/cacti.stderr.log"
	APACHE_ERROR="/var/log/apache2/error.log"
	APACHE_ACCESS="/var/log/apache2/access.log"
	LIGHT_ERROR="/var/log/lighttpd/error.log"
	RRA_ARCHIVE="/usr/share/cacti/site/rra/archive/"
	RRA_SHELL="/var/lib/cacti/rra/shell.php"
	POLLER="/usr/share/cacti/site/poller.php"
	WEBUSER="apache"
else
	echo "FATAL: Unsupported Platform"
	exit 127
fi

# ------------------------------------------------------------------------------
# Ensure that the artifact directory is created.  No need for a mess
# ------------------------------------------------------------------------------
if [ ! -d /tmp/check-all-pages ]; then
	mkdir /tmp/check-all-pages
fi

# ------------------------------------------------------------------------------
# Backup the error logs to capture what went wrong
# ------------------------------------------------------------------------------
save_log_files() {
	if [ $started == 1 ];then
		logBase=/tmp/check-all-pages/test.$(date +%s)
		mkdir -p $logBase

		echo "NOTE: Copying $CACTI_LOG to artifacts"
		cp $CACTI_LOG ${logBase}/cacti.log
		cp $CACTI_ERRLOG ${logBase}/cacti_error.log

		if [ -f $APACHE_ACCESS ] ; then
			echo "NOTE: Copying $APACHE_ACCESS to artifacts"
			cp $APACHE_ACCESS ${logBase}/apache_access.log
		fi

		if [ -f $APACHE_ERROR ] ; then
			echo "NOTE: Copying $APACHE_ERROR to artifacts"
			cp -f $APACHE_ERROR ${logBase}/apache_error.log
		fi

		if [ -f $LIGHT_ERROR ] ; then
			echo "NOTE: Copying $LIGHT_ERROR to artifacts"
			cp -f $LIGHT_ERROR ${logBase}/lighty_error.log
		fi

		if [ -f $logFile1 ]; then
			echo "NOTE: Copying $logFile1 to artifacts"
			cp -f $logFile1 ${logBase}/wget_error.log
		fi

		chmod a+r ${logBase}/*.log
	fi
}

# ------------------------------------------------------------------------------
# Some functions to handle settings consitently
# ------------------------------------------------------------------------------
restore_cacti_log() {
	echo "UPDATE cacti.settings SET value='$CACTI_LOG' WHERE name='path_cactilog' ;" | mysql -u"$database_user" -p"$database_pw" cacti
}

save_cacti_settings() {
	mysqldump -u"$database_user" -p"$database_pw" cacti settings user_auth > /tmp/check-all-pages/settings.sql
}

set_cacti_admin_password() {
	mysql -u"$database_user" -p"$database_pw" -e "UPDATE user_auth SET password=MD5('$login_pw') WHERE id = 1" cacti
}

restore_cacti_settings() {
	mysql -u"$database_user" -p"$database_pw" cacti < /tmp/check-all-pages/settings.sql
	rm -f /tmp/check-all-pages/settings.sql
}

enable_log_validation() {
	echo "UPDATE cacti.settings SET value='on' WHERE name='log_validation' ;" | mysql -u"$database_user" -p"$database_pw" cacti
}

set_log_level_none() {
	echo "UPDATE cacti.settings SET value='1' WHERE name='log_verbosity' ;" | mysql -u"$database_user" -p"$database_pw" cacti
}

set_log_level_normal() {
	echo "UPDATE cacti.settings SET value='2' WHERE name='log_verbosity' ;" | mysql -u"$database_user" -p"$database_pw" cacti
}

set_stderr_logging() {
	echo "REPLACE INTO cacti.settings (name, value) VALUES ('path_stderrlog', '$CACTI_ERRLOG');" | mysql -u"$database_user" -p"$database_pw" cacti
}

catch_error() {
	echo ""
	echo "WARNING: Process Interrupted.  Exiting ..."

	# Get rid of any jobs
	kill -SIGINT $(jobs -p)

	if [ -f /tmp/check-all-pages/cacti.cron ]; then
		mv /tmp/check-all-pages/cacti.cron /etc/cron.d/cacti
	fi

	if [ -f /tmp/check-all-pages/settings.sql ]; then
		restore_cacti_settings
	fi

	if [ -f $tmpFile1 ]; then
		rm -f $tmpFile1
	fi

	if [ -f $tmpFile2 ]; then
		rm -f $tmpFile2
	fi

	if [ -f $cookieFile ]; then
		rm -f $cookieFile
	fi

	save_log_files

	exit 0
}

# ------------------------------------------------------------------------------
# Make Cacti work again after the test is complete
# ------------------------------------------------------------------------------
restore_operating_environment() {
	# ------------------------------------------------------------------------------
	# Move the crontab file back 
	# ------------------------------------------------------------------------------
	/bin/mv -f /tmp/check-all-pages/cacti.cron /etc/cron.d/cacti

	# ------------------------------------------------------------------------------
	# Cleanup temp files
	# ------------------------------------------------------------------------------
	/bin/rm -f $tmpFile1
	/bin/rm -f $tmpFile2
	/bin/rm -f $logFile1
	/bin/rm -f $cookieFile
}

# ------------------------------------------------------------------------------
# To make sure that the autopkgtest/CI sites store the information
# ------------------------------------------------------------------------------
trap 'catch_error' 1 2 3 6 9 14 15

# ------------------------------------------------------------------------------
# Move the crontab line to prevent the cron from interfering
# ------------------------------------------------------------------------------
mv -f /etc/cron.d/cacti /tmp/check-all-pages/cacti.cron

SECONDS=15
echo "NOTE: Waiting for Cacti for $SECONDS seconds ..."
sleep $SECONDS 

# ------------------------------------------------------------------------------
# Zero out the log files
# ------------------------------------------------------------------------------
> $CACTI_LOG
> $CACTI_ERRLOG
> $APACHE_ERROR
> $APACHE_ACCESS
/bin/chown $WEBUSER:$WEBUSER $CACTI_LOG
/bin/chown $WEBUSER:$WEBUSER $CACTI_ERRLOG

# ------------------------------------------------------------------------------
# Get the current database password, which by default is also used for the
# admin.
# ------------------------------------------------------------------------------
database_user="cactiuser"
database_pw="cactiuser"
login_pw="admin"

# ------------------------------------------------------------------------------
# Make a backup copy of the Cacti settings table and enable log validation
# ------------------------------------------------------------------------------
save_cacti_settings
set_cacti_admin_password
enable_log_validation
set_stderr_logging

# ------------------------------------------------------------------------------
# Lighttpd is not the default httpd for cacti. If we are testing for it, we
# need to enable the conf first.
# ------------------------------------------------------------------------------
if [ -n "$(which lighttpd-enable-mod 2>/dev/null)" ] ; then
	echo "NOTE: Lighttpd Module Found"
    lighttpd-enable-mod cacti
    /etc/init.d/lighttpd force-reload
else
	echo "NOTE: Lighttpd Module Not Found"
fi

tmpFile1=$(mktemp)
tmpFile2=$(mktemp)
logFile1=$(mktemp)
cookieFile=$(mktemp)
loadSaveCookie="--load-cookies $cookieFile --keep-session-cookies --save-cookies $cookieFile"
started=1

# ------------------------------------------------------------------------------
# Make sure we get the magic, this is stored in the cookies for future use.
# ------------------------------------------------------------------------------
set_log_level_normal

echo "NOTE: Saving Cookie Data"
wget -q --keep-session-cookies --save-cookies "$cookieFile" --output-document="$tmpFile1" http://localhost/cacti/index.php

magic=$(grep "name='__csrf_magic' value=" "$tmpFile1" | sed "s/.*__csrf_magic' value=\"//" | sed "s/\" \/>//")
postData="action=login&login_username=admin&login_password=${login_pw}&__csrf_magic=${magic}"

echo "NOTE: Logging into the Cacti User Interface"
wget -q $loadSaveCookie --post-data="$postData" --output-document="$tmpFile2" http://localhost/cacti/index.php

# ------------------------------------------------------------------------------
# Now loop over all the available links (but don't log out and don't delete or
# remove, don't uninstall, enable or disable plugins stuff.
# ------------------------------------------------------------------------------
echo "NOTE: Recursively Checking all Pages - Note this will take several minutes!!!"
wget $loadSaveCookie --output-file="$logFile1" --reject-regex="(logout\.php|remove|delete|uninstall|install|disable|enable)" --recursive --level=0 --execute=robots=off http://localhost/cacti/index.php
error=$?

if [ $error -eq 8 ]; then
	errors=`grep "awaiting response... 404" $logFile1 | wc -l`
	echo "WARNING: $errors pages not found.  This is not necessarily a bug"
fi

checks=`grep "HTTP" $logFile1 | wc -l`
echo "NOTE: There were $checks pages checked through recursion"

# ------------------------------------------------------------------------------
# Make a backup copy of the Cacti settings table
# ------------------------------------------------------------------------------
restore_cacti_settings

# ------------------------------------------------------------------------------
# Finally check the cacti log for unexpected items
# ------------------------------------------------------------------------------
echo "NOTE: Checking Cacti Log for Errors"
FILTERED_LOG="$(grep -v \
	-e "AUTH LOGIN: User 'admin' Authenticated" \
	-e "WEBUI NOTE: Poller Resource Cache scheduled for rebuild by user admin" \
	-e "IMPORT NOTE: File is Signed Correctly" \
	-e "MAILER INFO:" \
	-e "STATS:" \
	-e "IMPORT Importing XML Data for " \
	-e "CMDPHP SQL Backtrace: " \
	$CACTI_LOG)" || true

save_log_files

# ------------------------------------------------------------------------------
# Look for errors in the Log
# ------------------------------------------------------------------------------
error=0
if [ -n "${FILTERED_LOG}" ] ; then
    echo "ERROR: Fail - Unexpected output in $CACTI_LOG:"
    echo "${FILTERED_LOG}"
	restore_operating_environment
	exit 179
else
    echo "NOTE: Success - No unexpected output in $CACTI_LOG"
	restore_operating_environment
	exit 0
fi

