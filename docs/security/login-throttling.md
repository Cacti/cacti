# Password login limits

Cacti reserves an attempt before verifying local or directory credentials. By default each client address can make 120 password attempts per five minutes. Each combination of client address and normalized login name can make ten attempts in that interval. A successful credential login releases its own reservation in both limits. Pending and failed attempts continue to count. Adjust the three login attempt settings under Authentication when a proxy or NAT serves many users. The account lockout settings continue to count credential failures independently.

Configure the trusted proxy list and permitted forwarded headers to match your deployment. Cacti uses the same resolved client address for login audit events and throttling. Basic authentication and identity provider redirects perform their primary challenge upstream.

All web nodes use the primary Cacti database for counters and expiration time. A disconnected remote collector cannot reserve an attempt. Database maintenance removes expired counters. Identifier buckets store hashes of the normalized keys.
