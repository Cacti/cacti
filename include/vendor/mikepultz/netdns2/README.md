# Net\_DNS2 - Native PHP DNS Resolver and Updater #

### The main features for this package include: ###

  * Increased performance; most requests are 2-10x faster than Net\_DNS
  * Near drop-in replacement for Net\_DNS
  * Uses modern PHP classes and exceptions
  * Support for IPv4 and IPv6, TCP and UDP sockets.
  * Includes a separate, more intuitive "Updater" class for handling dynamic update
  * Support zone signing using TSIG and SIG(0) for updates and zone transfers
  * Includes a local cache using shared memory or flat file to improve performance
  * includes many more RR's, including DNSSEC RR's.


## Installing Net\_DNS2 ##

You can require it directly via Composer: https://packagist.org/packages/pear/net_dns2

```
composer require pear/net_dns2
```

Or download the source above.

## Requirements ##

* PHP 7.1+ (as of v1.5.5)
* The PHP INI setting `mbstring.func_overload` equals 0, 1, 4, or 5.


## Using Net\_DNS2 ##

See the Net\_DNS2 Website for more details - https://netdns2.com/

