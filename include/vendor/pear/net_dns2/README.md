# NetDNS2 - PHP DNS Resolver and Updater

[![PHP version](https://img.shields.io/badge/php-%3E%3D%208.1-8892BF.svg)](https://github.com/mikepultz/netdns2) ![Packagist Downloads](https://img.shields.io/packagist/dt/pear/net_dns2) ![Tests](https://github.com/mikepultz/netdns2/actions/workflows/ci.yml/badge.svg)

The NetDNS2 library is a pure PHP DNS Resolver library, that supports local caching, dynamic DNS updates, and almost every feature currently supported by modern DNS servers.

### The main features for this package include:

  * PSR-4 style autoloading, and namespace semantics (as of v2.0)
  * Support for IPv4 and IPv6, UDP, TCP, and TLS sockets.
  * Support for DNS over TLS (DoT).
  * Support for DNS over HTTP (DoH) using RFC 8484 application/dns-message format.
  * Support for all defined (and not obsoleted) resource record types.
  * Support for Internationalized Domain Names (IDN) (e.g. Punycode)
  * Support for DNSSEC requests and resource records.
  * Support for EDNS(0) features (client subnet,  cookies, TCP keepalive, etc.)
  * Support zone signing using TSIG and SIG(0) for updates and zone transfers.
  * Includes a separate Updater class for handling dynamic DNS updates.
  * Includes a separate Notifier class for sending DNS notification messages.
  * Includes a local cache using shared memory, flat file, Memcached, or Redis to improve performance.

## Installing NetDNS2

You can require it directly via Composer: https://packagist.org/packages/pear/net_dns2

```
composer require pear/net_dns2
```

Or download the source above.

## Upgrading - IMPORTANT!

**NetDNS2 v2.x changed to a PSR-4 style layout using namespaces, so moving from v1.x to v2.x requires updating your code to match to the new semantics.**

For example:

v1.x

    try  
    {
        $r = new Net_DNS2_Resolver([ 'nameservers' => [ '192.168.0.1' ]]);

        $res = $r->query('google.com', 'MX');

    } catch(Net_DNS2_Exception $e)
    {     
        print_r($e);
    }


v2.x

    try   
    {
        $r = new \NetDNS2\Resolver([ 'nameservers' => [ '192.168.0.1' ]]);

        $res = $r->query('google.com', 'MX');

    } catch(\NetDNS2\Exception $e)  
    {
        print_r($e);
    }


While all the underlying objects have been reorganized into namespaces, the majority of the class, function, and property names have remained the same, so upgrading should not require too many changes.

To continue using NetDNS2 v1.x, update your `composer.json` file to specify 1.x, e.g.:

    {
        "require": {
            "pear/net_dns2": "^1.5"
        }
    }

Version 1.x will be maintained for the foreseeable  future.

## Requirements

* PHP 8.1+ - this version uses strong typing, ENUMs, and other modern PHP features, and will not work with older versions of PHP.
* (OPTIONAL) [OpenSSL](https://www.php.net/manual/en/book.openssl.php) - for DNS over TLS (DoT) and certain resource record types.
* (OPTIONAL) [cURL](https://www.php.net/manual/en/book.curl.php) - for DNS over HTTP (DoH).
* (OPTIONAL) [Hash](https://www.php.net/manual/en/ref.hash.php) - for TSIG request authentication.
* (OPTIONAL) [Intl](https://www.php.net/manual/en/book.intl.php) - for decoding Unicode domains.
* (OPTIONAL) [Shmop](https://www.php.net/manual/en/book.shmop.php) - for local caching
* (OPTIONAL) [Memcached](https://www.php.net/manual/en/book.memcached.php) - for local caching.
* (OPTIONAL) [Redis](https://github.com/phpredis/phpredis/) - for local caching.

## Using NetDNS2

* [Config Options](#config-options)
* [Basic Examples](#basic-examples)
* [DNS over TLS (DoT)](#dot)
* [DNS over HTTP (DoH)](#doh)
* [DNSSEC with Signature Verification](#dnssec)
* [Data Objects](#objects)
* [Internationalized Domain Names (IDN)](#unicode)
* [IPv6 Support](#ipv6)
* [Local Cache](#cache)
    * [Flat File](#file)
    * [Shared Memory (SHM)](#shm)
    * [Memcached](#memcached)
    * [Redis](#redis)
* [DNS Updates](#updates)
* [DNS Notifications](#notifications)
* [EDNS(0) Support](#edns)
* [Request Signing](#signing)
    * [TSIG](#tsig)
    * [SIG(0)](#sig)

### <a name="config-options"></a>Config Options 

Configuration options can be passed to the `NetDNS2\Resolver`, `NetDNS2\Updater`, and `NetDNS2\Notifier` constructors, or as properties after the object is initialized.

    $r = new \NetDNS2\Resolver(
    [
        //
        // an array of IP addresses to use as name servers. If this is unset it will 
        // default to using the /etc/resolv.conf file.
        //
        // array, defaults to unset.
        //
        'nameservers'   => [ '1.1.1.1', '8.8.8.8' ],

        //
        // tells NetDNS2 to randomize the name servers list each time it’s used.
        //
        // boolean, defaults to false
        //
        'ns_random'     => true,

        //
        // timeout value to use for socket connections, provided as float, with microsecond 
        // precision. e.g. a value of 1.0 will timeout in 1 second. A value of 0.05 will 
        // timeout in 50 milliseconds.
        //
        // float, defaults to 5 seconds
        //
        'timeout'       => 0.05,

        //
        // tells NetDNS2 to use TCP instead of UDP for queries. UDP is faster, but is limited 
        // in size. NetDNS2 will automatically use TCP for zone transfers (AFXR) and when 
        // a response was truncated.
        //
        // in the event of a truncated response, NetDNS2 will switch to TCP, and resend the 
        // request.
        //
        // boolean, defaults to false.
        //
        'use_tcp'       => true,

        //
        // use DNS over TLS (DoT) this requires OpenSSL support enabled in PHP
        //
        // enabling this option will also enable use_tcp, and sets the default port to 853
        //
        // boolean, defaults to false.
        //
        'use_tls'       => true,

        //
        // if set, these values are passed to stream_context_create() as the 'ssl' transport 
        // section, which lets you customize TLS connection settings.
        //
        // only applies when use_tls = true
        //
        // array, defaults to empty
        //
        'tls_context'   => [ 'verify_peer' => false, 'verify_peer_name' => false ],

        //
        // DNS Port to use; -1 means default of 53 (or 853 when using DoT)
        //
        // int, defaults to -1
        //
        'dns_port'      => 53,

        //
        // the local IP address to bind to when making outbound requests.
        //
        // string, defaults to unset.
        //
        'local_host'    => '',

        //
        // the local port number to bind to when making outbound requests. local_host can be 
        // set without this setting, and the system will auto-allocate the port from the 
        // ephemeral ports.
        //
        // int, defaults to 0 (unset)
        //
        'local_port'    => 0,

        //
        // The default domain to use for unqualified host names.
        //
        // string, defaults to unset
        //
        'domain'        => 'netdns2.com',

        //
        // defines the type of cache to use, using a pre-defined ENUM option. valid options 
        // are:
        //
        //  \NetDNS2\Cache::CACHE_TYPE_NONE         - disables the local cache
        //  \NetDNS2\Cache::CACHE_TYPE_FILE         - flat file cache
        //  \NetDNS2\Cache::CACHE_TYPE_SHM          - shared memory (requires Shmop extension)
        //  \NetDNS2\Cache::CACHE_TYPE_MEMCACHED    - memcache (requires Memcached extension)
        //  \NetDNS2\Cache::CACHE_TYPE_REDIS        - redis (requires Redis extension)
        //
        // int, defaults to \NetDNS2\Cache::CACHE_TYPE_NONE
        //
        'cache_type'    => \NetDNS2\Cache::CACHE_TYPE_MEMCACHED,

        //
        // options to pass to the underlying caching objects
        //
        // array, defaults to empty
        //
        'cache_options' => [ 

            'server' => [

                [ '127.0.0.1', 11211 ]
            ],
            'options' => [

                \Memcached::OPT_COMPRESSION => true
            ]
        ],

        //
        // strict_query_mode means that if the hostname that was looked up isn’t actually in 
        // the answer section of the response, NetDNS2 will return an empty answer section, 
        // instead of an answer section that could contain CNAME records.
        //
        // boolean, defaults to false
        //
        'strict_query_mode' => true,

        //
        // if we should set the recursion desired bit to 1 or 0.
        //
        // by default this is set to true, we want the DNS server to perform a recursive 
        // request. If set to false, the RD bit will be set to 0, and the server will not 
        // perform recursion on the request.
        //
        // boolean, defaults to true
        //
        'recurse'   => true,

        //
        // request DNSSEC values, by setting the DO flag to 1
        //
        // this instructs the upstream resolvers that we want to include DNSSEC details
        // with our request, if supported by the zone.
        //
        // boolean, defaults to false
        //
        'dnssec'    => false,

        //
        // set the DNSSEC AD (Authentic Data) bit on/off.
        //
        // this isn't used by client connections, as the upstream resolver is responsible
        // for verifying the DNSSEC signatures and setting this bit.
        //
        // boolean, defaults to false
        //
        'dnssec_ad_flag'    => false,

        //
        // set the DNSSEC CD (Checking Disabled) bit on/off
        //
        // this instructs the upstream resolvers to validate DNSSEC signatures in the
        // response (when the dnssec option is true).
        //
        // if set to true, it signals the upstream resolvers to return the DNS records
        // regardless of whether they are cryptographically verifiable. 
        //
        // boolean, defaults to false
        //
        'dnssec_cd_flag'    => false,

        //
        // the EDNS(0) UDP payload size to use when making DNSSEC requests; see RFC 2671 
        // section 6.2.3 for more details
        //
        // integer, defaults to 1280
        //
        'dnssec_payload_size'   => 4000
    ]);

Configuration options can also be set as properties after the object is initialized, for example:

    $r = new \NetDNS2\Resolver();

    $r->timeout = 1.5;
    $r->dnssec = true;

    $res = $r->query('google.com', 'A');

### <a name="basic-examples"></a>Basic Examples

The main `NetDNS2\Resolver` class is used to look up DNS records.

#### Perform a Simple A Record Lookup on facebook.com

    try
    {
        //
        // create new resolver object, passing in an array of name servers to use for lookups
        //
        $r = new \NetDNS2\Resolver(['nameservers' => [ '1.1.1.1' ]]);

        //
        // execute the query
        //
        $res = $r->query('facebook.com', 'A');

        //
        // if facebook points to more than one IP, then you can loop through the answer array to
        // see each IP address.
        //
        echo "facebook resolves to: " . $res->answer[0]->address;

    } catch(\NetDNS2\Exception $e)
    {
        echo "::query() failed: " . $e->getMessage() . "\n";
    }


#### Get the MX Records for Google.com

    try
    {
        //
        // create new resolver object, passing in an array of name servers to use for lookups
        //
        $r = new \NetDNS2\Resolver(['nameservers' => [ '1.1.1.1' ]]);

        //
        // execute the query request for the google.com MX servers
        //
        $res = $r->query('google.com', 'MX');

        //
        // loop through the answer, printing out the MX servers returned.
        //
        foreach($res->answer as $mxrr)
        {
            printf("preference=%d, host=%s\n", $mxrr->preference, $mxrr->exchange);
        }

    } catch(\NetDNS2\Exception $e)
    {
        echo "::query() failed: " . $e->getMessage() . "\n";
    }

#### Zone Transfer (AXFR) for example.com


    try
    {
        //
        // create new resolver object, passing in an array of name servers to use for lookups
        //
        $r = new \NetDNS2\Resolver([ 'nameservers' => [ '192.168.0.1' ]]);

        //
        // add a TSIG to authenticate the request
        //
        $r->signTSIG('mykey', '9dnf93asdf39fs');

        //
        // execute the query request for the google.com MX servers
        //
        $res = $r->query('example.com', 'AXFR');

        //
        // loop through the answer, printing out each resource record.
        //
        foreach($res->answer as $rr)
        {
            print_r($rr);
        }

    } catch(\NetDNS2\Exception $e)
    {
        echo "::query() failed: ", $e->getMessage(), "\n";
    }


### <a name="dot"></a>DNS over TLS (DoT)

DNS over TLS (DoT) is supported since NetDNS2 v2.0, and requires the [OpenSSL](https://www.php.net/manual/en/book.openssl.php) extension.

To enable DoT, simply set the `use_tls` option to true, for example:

    try
    {
        //
        // create new resolver object, passing in an array of name servers to use for lookups
        //
        $r = new \NetDNS2\Resolver(['nameservers' => [ '1.1.1.1' ]]);

        //
        // enable DoT
        //
        $r->use_tls = true;

        //
        // execute the query request for the google.com MX servers
        //
        $res = $r->query('facebook.com', 'A');

        //
        // if facebook points to more than one IP, then you can loop through the answer array to
        // see each IP address.
        //
        echo "facebook resolves to: " . $res->answer[0]->address;

    } catch(\NetDNS2\Exception $e)
    {
        echo "::query() failed: " . $e->getMessage() . "\n";
    }

Setting this option will change the default port to 853 (this can be overridden by setting the `dns_port` option), and change the library to use TCP sockets instead of UDP.

Additional TLS specific options can be passed using the `tls_context` array, which are passed directly to the `stream_context_create()` function when setting up the TLS socket. For example, to disable TLS peer verifications on requests:

    $r->use_tls = true;

    $r->tls_context = [ 'verify_peer' => false, 'verify_peer_name' => false ];

For more details, see the [SSL Context Options](https://www.php.net/manual/en/context.ssl.php) documentation. 

### <a name="doh"></a>DNS over HTTP (DoH)

DNS over HTTP (DoH) is supported since v2.0, and requires the [cURL](https://www.php.net/manual/en/book.curl.php) extension.

To enable DoH, simple pass name server values as URLs instead of IP addresses, for example:

    try
    {
        //
        // create new resolver object, passing in an array of DoH servers
        //
        $r = new \NetDNS2\Resolver(['nameservers' => [ 'https://cloudflare-dns.com/dns-query' ]]);

        //
        // execute the query request for the facebook A record
        //
        $res = $r->query('facebook.com', 'A');

        //
        // if facebook points to more than one IP, then you can loop through the answer array to
        // see each IP address.
        //
        echo "facebook resolves to: " . $res->answer[0]->address;

    } catch(\NetDNS2\Exception $e)
    {
        echo "::query() failed: " . $e->getMessage() . "\n";
    }
    
NetDNS2 performs DoH requests according to [RFC 8484](https://datatracker.ietf.org/doc/html/rfc8484), using `application/dns-message` (wire format) formatted messages (not using JSON), over HTTPS (HTTP is not supported).

>DoH has not currently be tested with DNS Updates - support for this is undefined.

### <a name="dnssec"></a>DNSSEC with Signature Verification

To request DNSSEC verification, simply set the `dnssec` flag to `true` when making a request, for example:

    try
    {
        //
        // create new resolver object, passing in an array of name servers to use for lookups
        //
        $r = new \NetDNS2\Resolver(['nameservers' => [ '1.1.1.1' ]]);

        //
        // request DNSSEC records
        //
        $r->dnssec = true;

        //
        // execute the query
        //
        $res = $r->query('org', 'SOA');

        //
        // check the ad flag; if it's set to 1, then the upstream resolver is confirming that
        // the DNSSEC verification was successful and we can trust the results.
        //
        if ($res->header->ad == 1)
        {
            echo "DNSSEC verification success!";
        } else
        {
            echo "DNSSEC verification failure; we can't trust this response.";
        }

    } catch(\NetDNS2\Exception $e)
    {
        echo "::query() failed: " . $e->getMessage() . "\n";
    }

If the zone supports DNSSEC, then the additional DNSSEC records will be returned, and the `ad` bit will be adjusted in the header to indicate if the verification was successful.

The `dnssec_cd_flag` argument (aka "DNSSEC Checking Disabled") flag is unset by default. You can optionally set this flag to `true` to have the upstream resolver return the DNS records regardless of whether they are cryptographically verifiable.

    //
    // allow unverifiable DNSSEC results
    //
    $r->dnssec_cd_flag = true;

Keep in mind, many zones do not support DNSSEC, so failing because the `ad` bit is 0 for all domains may not be what you want.

### <a name="objects"></a>Data Objects

NetDNS2 v2.0 uses data objects to store domain names, IPv4 & IPv6 addresses, text entries, and mailboxes, extended from the `\NetDNS2\Data` class.

For example:

    $res = $r->query('facebook.com', 'A');

    echo "facebook resolves to: " . $res->answer[0]->address;

The `$address` value is an instance of a `\NetDNS2\Data\IPv4` object; to get the value, you can echo it (e.g. using the Stringable interface):

    echo $res->answer[0]->address;

The only time this may cause a problem is cases where you're passing the `$address` value to a function that takes a string as an argument. In those cases, you can use the built-in `value()` function to return the value as a string:

    do_something($res->answer[0]->address->value());

Or you can cast the value to a string using `strval()`:

    do_something(strval($res->answer[0]->address));

### <a name="unicode"></a>Internationalized Domain Names (IDN)

NetDNS2 v2.0.2 supports Unicode domain names using the PHP Intl extension.

For example:

    $res = $r->query('域名.中国', 'CNAME');

    echo "域名.中国 resolves to: " . $res->answer[0]->cname;

The `$name` value is converted to the Punycode value `xn--eqrt2g.xn--fiqs8s` internally. The value is converted back to Unicode when used or printed:

    echo "The name value is: " . $res->answer[0]->name;

If the `$name` value is already ASCII, or the Intl extension is not installed, then the value is left as-is.

### <a name="ipv6"></a>IPv6 Support

NetDNS2 includes full support for IPv6. This includes support for accessing IPv6 DNS servers, forward DNS lookups (the AAAA record), and reverse DNS (PTR) support, using long or short form IPv6 IP addresses.

#### IPv6 DNS Servers

Set in a resolv.conf file, or directly in the namservers array.

    $r = new \NetDNS2\Resolver([ 'nameservers' => [ '::1' ]]);

#### IPv6 Reverse DNS

    $res = $r->query('::1', 'PTR');

    1.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.ip6.arpa. 86400 IN PTR localhost.

#### IPv6 Forward DNS (AAAA)

    $res = $r->query('a2.test.com', 'AAAA');

    a2.test.com. 86400 IN AAAA ff01:0:0:0:0:0:0:43

All resource records that support IPv6 (AMTRELAY, APL, SVCB, IPSECKEY, etc.) are supported by NetDNS2.

### <a name="cache"></a>Local Cache

NetDNS2 includes a built-in local cache to improve query performance. The cache is disabled by default, and currently supports:

* flat file cache (local disk)
* shared memory (using the [Shmop](https://www.php.net/manual/en/book.shmop.php) extension)
* memcache (using the [Memcached](https://www.php.net/manual/en/book.memcached.php) extension)
* redis (using the [Redis](https://github.com/phpredis/phpredis/) extension)

The local cache is only used for lookup queries, and is disabled for Updates.

Cached data is stored as a serialized string, using the standard PHP serialize().

>Previous versions of NetDNS2 supported using JSON as the data serializer, but this functionality was removed in v2.0.

#### <a name="file"></a>Flat File

NetDNS2 can use a flat file to store the cache information. The `file` must be in a location PHP can write to, and has enough space to hold a file `size` bytes big.

    //
    // create a new Resolver object
    //
    $r = new \NetDNS2\Resolver(
    [ 
        'nameservers'   => [ '192.168.0.1' ]

        'cache_type'    => \NetDNS2\Cache::CACHE_TYPE_FILE,
        'cache_options' => [

            'file'  => '/tmp/cache.txt',    // the file to serialize cache content to
            'size'  => 50000,               // the max file size for this cache file

            'ttl_override' => 300           // int, in seconds, to cache data for
        ]
    ]);

The `ttl_override` option is supported by all Caching extensions, and lets you override the Cache duration, which normally defaults to the TTL value on the response.

#### <a name="shm"></a>Shared Memory (Shm)

NetDNS2 uses the [Shmop](https://www.php.net/manual/en/book.shmop.php) Extension. If you do not have the extension installed, and you specify to use the shared memory cache, NetDNS2 will throw an exception.

    //
    // create a new Resolver object
    //
    $r = new \NetDNS2\Resolver(
    [ 
        'nameservers'   => [ '192.168.0.1' ]

        'cache_type'    => \NetDNS2\Cache::CACHE_TYPE_SHM,
        'cache_options' => [

            'file'  => '/tmp/cache.txt',    // the file to serialize cache content to
            'size'  => 50000                // the max file size for this cache file
        ]
    ]);

`file` is used as an System V IPC key (using ftok())

#### <a name="memcached"></a>Memcached

NetDNS2 v2.x and up support caching DNS results in memcache, using the [Memcached](https://www.php.net/manual/en/book.memcached.php) extension.

    //
    // create a new Resolver object
    //
    $r = new \NetDNS2\Resolver(
    [ 
        'nameservers'   => [ '192.168.0.1' ]

        'cache_type'    => \NetDNS2\Cache::CACHE_TYPE_MEMCACHED,
        'cache_options' => [

            'server' => [

                [ '127.0.0.1', 11211 ]
            ],
            'options' => [

                \Memcached::OPT_COMPRESSION => true
            ]
        ]
    ]);

The `server` section of the `cache_options` property is passed directly to the [\Memcached::addServers()](https://www.php.net/manual/en/memcached.addservers.php) function, and lets you specify one or more Memcached servers to use. The `options` section of the `cache_options` property is passed directly to the [\Memcached::setOptions()](https://www.php.net/manual/en/memcached.setoptions.php) function, and lets you pass any of the available Memcache options.

#### <a name="redis"></a>Redis

NetDNS2 v2.x and up support caching DNS results in Redis, using the [Redis](https://github.com/phpredis/phpredis/) extension. The current implementation does not support the RedisCluster object yet.

    $r = new \NetDNS2\Resolver(
    [ 
        'nameservers'   => [ '192.168.0.1' ]

        'cache_type'    => \NetDNS2\Cache::CACHE_TYPE_REDIS,
        'cache_options' => [

            'host' => '127.0.0.1',
            'port' => 6379
        ]
    ]);

The `cache_options` array is passed directly to the `Redis` constructor, so all configuration options are supported. See [phpredis documentation](https://github.com/phpredis/phpredis/?tab=readme-ov-file#class-redis) for the full list of configuration options.

### <a name="updates"></a>DNS Updates

NetDNS2 supports dynamic DNS updates per [RFC 2136](https://datatracker.ietf.org/doc/html/rfc2136) using the `NetDNS2\Updater` object.

When creating the object, you must pass the domain (zone) you'll be modifying, as well as the primary authoritative DNS server hosting the zone.

    //
    // create a new Updater object
    //
    $u = new \NetDNS2\Updater('example.com', [ 'nameservers' => [ '192.168.0.1' ]]);

There are several operations defined in RFC 2136, 

    //
    // add a new entry - pass an instance of a RR object
    //
    $u->add(\NetDNS2\RR::fromString('test.example.com 600 IN A 2.2.2.2'));

    //
    // delete an existing entry
    //
    $u->delete(\NetDNS2\RR::fromString('test.example.com 600 IN A 2.2.2.2'));

    //
    // delete any MX records on the example.com domain
    //
    $u->deleteAny('example.com', 'MX');

    //
    // delete all records for example.com
    //
    $u->deleteAll('example.com');

    //
    // check to see if example.com has any MX records
    //
    $u->checkExists('example.com', 'MX');

    //
    // check if the specific RR exists
    //
    $u->checkValueExists(\NetDNS2\RR::fromString('test.example.com 600 IN A 2.2.2.2'));

    //
    // check if the example.com does NOT have a TXT record
    //
    $u->checkNotExists('example.com', 'TXT');

    //
    // check if the given name is in use by any RR
    //
    $u->checkNameInUse('test.example.com');

    //
    // check if the given name is NOT in use by any RR
    //
    $u->checkNameNotInUse('test.example.com');

You can add multiple queries in a single object; once you're ready to submit, you call the `update()` function to execute all commands.

    //
    // send the update request.
    //
    $u->update();

### <a name="notifications"></a>DNS Notifications

The `NetDNS2\Notifier` class provides functionality to perform DNS notify requests as defined by [RFC 1996](https://datatracker.ietf.org/doc/html/rfc1996).

This is separate from the `\NetDNS2\Resolver` class, as while the underlying protocol is the same, the functionality is completely different. Generally, query (recursive) lookups are done against caching server, while notify requests are done against authoritative servers.

A simple example:

    //
    // create a new Notifier object
    //
    $n = new \NetDNS2\Notifier('netdns2.com', ['nameservers' => [ '192.168.0.1' ]]);

    //
    // add an optional TSIG to authenticate the request
    //
    $n->signTSIG('mykey', '9dnf93asdf39fs');

    //
    // add a resource record to trigger the notify against the secondary servers
    //
    $n->add(\NetDNS2\RR::fromString('test.netdns2.com 600 IN A 2.2.2.2'));

    //
    // trigger a notify request
    //
    $n->notify();

This functionality is often triggered directly from the primary DNS server, but using this object, you can trigger a DNS sync programmatically.

### <a name="edns"></a>EDNS(0) Support

NetDNS2 support most EDNS(0) options via an `edns` object included in the main Client class.

#### Examples

Include a client subnet in a query ([RFC 7871](https://datatracker.ietf.org/doc/html/rfc7871)):

    //
    // create a new Resolver object
    //
    $r = new \NetDNS2\Resolver([ 'nameservers' => [ '192.168.0.1' ]]);

    //
    // enable the client subnet option, and pass in my IP range
    //
    $r->edns->client_subnet(true, '10.10.10.0/24');

    //
    // request the A record
    //
    $res = $r->query('example.com', 'A');

You can enable multiple EDNS options in a single query

    //
    // create a new Resolver object
    //
    $r = new \NetDNS2\Resolver([ 'nameservers' => [ '192.168.0.1' ]]);

    //
    // enable the client subnet option, and pass in my IP range
    //
    $r->edns->client_subnet(true, '10.10.10.0/24');

    //
    // set a TCP keepalive value
    //
    $r->edns->tcp_keepalive(true, 300);

    //
    // request name server identifier information
    //
    $r->edns->nsid(true);

    //
    // request the A record
    //
    $res = $r->query('example.com', 'A');

You can also remove a previously added option if needed, before you execute the `query()` function:

    //
    // create a new Resolver object
    //
    $r = new \NetDNS2\Resolver([ 'nameservers' => [ '192.168.0.1' ]]);

    //
    // enable the client subnet option, and pass in my IP range
    //
    $r->edns->client_subnet(true, '10.10.10.0/24');

    //
    // change my mind, and remove the option
    //
    $r->edns->client_subnet(false);

    //
    // request the A record
    //
    $res = $r->query('example.com', 'A');

#### Supported Options

##### Update Lease - https://datatracker.ietf.org/doc/draft-ietf-dnssd-update-lease/09/

    //
    // update_lease(boolean enable, int desired_lease_time, int desired_key_lease_time = 0)
    //
    $u->edns->update_lease(true, time());

##### DNS Name Server Identifier (NSID) - [RFC 5001](https://datatracker.ietf.org/doc/html/rfc5001)

    //
    // nsid(boolean enable)
    //
    $r->edns->nsid(true);

##### DAU, DHU, and N3U - [RFC 6975](https://datatracker.ietf.org/doc/html/rfc6975)

    //
    // dau(boolean enable, array supported_dnssec_algorithms)
    //
    $r->edns->dau(true, [ 9, 10, 16 ]);

    //
    // dhu(boolean enable, array supported_hash_algorithms)
    //
    $r->edns->dhu(true, [ 2, 3, 4]);

    //
    // n3u(boolean enable, array supported_nsec3_algorithms)
    //
    $r->edns->n3u(true, [ 6, 7 ]);

##### Client Subnet in DNS Queries - [RFC 7871](https://datatracker.ietf.org/doc/html/rfc7871)

You can pass an IPv4 or IPv6 host `10.10.10.10` or subnet `10.10.10.0/24`, or `0.0.0.0/0` to signal to the resolver that client's address information must not be used when resolving this query.

    //
    // client_subnet(boolean enable, string address)
    //
    $r->edns->client_subnet(true, '2607:f8b0:4009:81a::200e/56');

##### Expire - [RFC 7314](https://datatracker.ietf.org/doc/html/rfc7314)

    //
    // expire(boolean enable)
    //
    $r->edns->expire(true);

##### Cookies - [RFC 7873](https://datatracker.ietf.org/doc/html/rfc7873)

    //
    // cookie(boolean enable, string cookie_string)
    //
    $r->edns->cookie(true, '3132333435363738');

The cookie value should be provided as a 8 character fixed-size value, hex-encoded (so the value passed in is 16 characters). According to RFC 7873 section 4.1:

>The Client Cookie SHOULD be a pseudorandom function of the Client IP Address, the Server IP Address, and a secret quantity known only to the client.  This Client Secret SHOULD have at least 64 bits of entropy [RFC4086] and be changed periodically (see Section 7.1).

##### TCP Keepalive - [RFC 7828](https://datatracker.ietf.org/doc/html/rfc7828)

The `timeout` value is the idle timeout value for the TCP connection, specified in units of 100 milliseconds.

    //
    // tcp_keepalive(boolean enable, int timeout)
    //
    $r->edns->tcp_keepalive(true, 300);

This option only makes sense when `use_tcp` is also set to true.

##### Chain - [RFC 7901](https://datatracker.ietf.org/doc/html/rfc7901)

    //
    // chain(boolean enable, string fqdn_of_closest_trust_point)
    //
    $r->edns->chain(true, 'com.');

##### Key Tag - [RFC 8145](https://datatracker.ietf.org/doc/html/rfc8145)

    //
    // key_tag(boolean enable, array list_of_key_tags)
    //
    $r->edns->key_tag(true, [ 12345, 67890 ]);

##### Extended DNS Errors - [RFC 8914](https://datatracker.ietf.org/doc/html/rfc8914)

    //
    // extended_error(boolean enable)
    //
    $r->edns->extended_error(true);

##### DNS Error Reporting - [RFC 9567](https://datatracker.ietf.org/doc/html/rfc9567)

    //
    // report_channel(boolean enable, string agent_domain)
    //
    $r->edns->report_channel(true, 'example.com');

##### DNS Zone Version - [RFC 9660](https://datatracker.ietf.org/doc/html/rfc9660)

    //
    // zone_version(boolean enable)
    //
    $r->edns->zone_version(true);

### <a name="signing"></a>Request Signing

NetDNS2 has support to sign outgoing requests using `TSIG` and `SIG(0)` (asymmetric private/public key) authentication. `NetDNS2\Resolver` (for zone transfers), `NetDNS2\Updater` (dynamic DNS updates), and `NetDNS2\Notifier` requests can be signed using either authentication type.

#### <a name="tsig"></a>TSIG

A TSIG (Transaction SIGnature) can be added to the request to authenticate the request. See [RFC 2845](https://datatracker.ietf.org/doc/html/rfc2845) for more details.

In BIND, a zone can be setup to allow updates using a TSIG like:

    key "mykey" {
        algorithm hmac-sha256;
        secret "9dnf93asdf39fs";
    };

    zone "example.com" {
        type master;
        file "dynamic/example.com";

        allow-transfer {
            key "mykey";
        }

        allow-update {
            key "mykey";
        };
    };

Then, using NetDNS2, you can execute:

    //
    // create a new Updater object
    //
    $u = new \NetDNS2\Updater('example.com', [ 'nameservers' => [ '192.168.0.1' ]]);

    //
    // add a TSIG to authenticate the request
    //
    $u->signTSIG('mykey', '9dnf93asdf39fs');

    //
    // send the update request.
    //
    $u->update();


#### <a name="sig"></a>SIG(0)
Signing using SIG(0) is more complicated. It requires a private/public key to be generated. Both can be generated using the `dnssec-keygen` tool. This tool produces both the public key, which will be advertised via the domain zone, and a private key which is passed to the `signSIG0()` function.

NetDNS2 uses the PHP [OpenSSL](https://www.php.net/manual/en/book.openssl.php) Extension to support SIG(0). NetDNS2 will throw an exception if you try to sign requests using SIG(0) and you do not have OpenSSL installed.

    //
    // create a new Updater object
    //
    $u = new \NetDNS2\Updater('example.com', [ 'nameservers' => [ '192.168.0.1' ]]);

    //
    // add a SIG(0) to authenticate the request; this is the path to the private key file
    //
    $u->signSIG0('/etc/named/Kexample.com.+001+15765.private');

    //
    // send the update request.
    //
    $u->update();


#### Zone Transfers (AXFR) & Notifications

The sign `signTSIG()` and `signSIG0()` functions can be used to authenticate zone transfers:

    //
    // create a new Resolver object
    //
    $r = new \NetDNS2\Resolver([ 'nameservers' => [ '192.168.0.1' ]]);

    //
    // add a SIG(0) to authenticate the request
    //
    $r->signSIG0('/etc/named/Kexample.com.+001+15765.private');

    //
    // request the zone transfer
    //
    $res = $r->query('example.com', 'AXFR');

and to authenticate Notification requests:

    //
    // create a new Notifier object
    //
    $n = new \NetDNS2\Notifier('netdns2.com', ['nameservers' => [ '192.168.0.1' ]]);

    //
    // add a TSIG to authenticate the request
    //
    $n->signTSIG('mykey', '9dnf93asdf39fs');

    //
    // trigger a notify request
    //
    $n->notify();
