#!/bin/sh
# Capture PHPMailer's real output without contacting a mail server.
set -eu
cat >> "${CACTI_ADMIN_ALERT_MAILBOX:?}"
printf '\nCACTI-END-MESSAGE\n' >> "$CACTI_ADMIN_ALERT_MAILBOX"
