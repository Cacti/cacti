#!/bin/bash
# Cacti 1.2 E2E Security Probe: Command Injection Neutralization
#
# This test attempts to trigger a command injection via a Data Input Method
# and verifies that the architectural hardening neutralizes it.

CONTAINER_NAME="cacti-e2e-server"
SUCCESS=1

echo "--- Starting Security Probe: Command Injection Neutralization ---"

# 1. Create a malicious payload
# This attempts to create a file in /tmp/cacti_hacked via a subcommand injection
PAYLOAD="127.0.0.1; touch /tmp/cacti_hacked"

# 2. Trigger the poller or a script that uses raw execution
# In Cacti 1.2, many scripts use cacti_exec or raw shell_exec.
# We will use the 'poller.php' script which we refactored.
echo "Attempting injection via poller-id parameter..."
docker exec $CONTAINER_NAME php /var/www/html/poller.php --poller-id="$PAYLOAD" > /dev/null 2>&1

# 3. Verify neutralization
# If the hardening is working, /tmp/cacti_hacked should NOT exist.
# The payload was treated as a literal argument.
if docker exec $CONTAINER_NAME ls /tmp/cacti_hacked > /dev/null 2>&1; then
    echo "FAIL: Command injection SUCCEEDED! System is vulnerable."
    docker exec $CONTAINER_NAME rm /tmp/cacti_hacked
    SUCCESS=0
else
    echo "PASS: Command injection neutralized by array-based contract."
fi

# 4. Repeat for SNMP community string (if snmpget is installed in container)
echo "Attempting injection via SNMP community string..."
docker exec $CONTAINER_NAME php /var/www/html/poller_snmpget.php -c "$PAYLOAD" .1.3.6.1.2.1.1.1.0 > /dev/null 2>&1

if docker exec $CONTAINER_NAME ls /tmp/cacti_hacked > /dev/null 2>&1; then
    echo "FAIL: SNMP injection SUCCEEDED! System is vulnerable."
    docker exec $CONTAINER_NAME rm /tmp/cacti_hacked
    SUCCESS=0
else
    echo "PASS: SNMP injection neutralized."
fi

echo "--- Security Probe Complete ---"

if [ $SUCCESS -eq 1 ]; then
    exit 0
else
    exit 1
fi
