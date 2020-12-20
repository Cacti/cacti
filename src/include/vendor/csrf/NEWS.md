# News

## 1.1.0 released 2020-02-23

Bug Fixes

- In some server environments, being behind a load balancer and enabling IP
  restrictions would be ineffective as other users would all appear to be from
  the same remote address

Features

- Allow logging of the CSRF process which is useful for third party develoeprs
  when they are trying to incorporate the library to see what steps are being
  taken and from where.

  Configuration var: `log_file`

- Allow logging to also be echoed to console

  Configuration var: `log_echo`

- Allow specifying the location of the CSRF secret file as some package
  maintainers may prefer to relocate the secret file to a hidden location that
  is readable only when installing the package and not be the application.

  Configuration var: `path_secret`

- Allow specifying the startup function as some callers may wish to keep in line
  with their own code formats.

  Configuration var `startup_func`

- Allow configuration of which hashing function to use.  It is expected that the
  user configuring this option will known what hash functions are availble or it
  could cause runtime errors.

  Configuration var `hash`

- Allow use of session_id() to be configured via configuration variable so that
  other mechanisms can be utilsed instead.

  Configuration var `session`

## 1.0.5 released 2014-07-24

Bug Fixes

- In some server environments, IP address was not being detected properly.
  Thanks Bianka Martinovic for reporting.

Security Fixes

- Hashing now uses an HMAC to prevent length extension attacks.

Features

- New option 'disable' which allows you to conditionally disable the CSRF
  protection.  Requested by Justin Carlson.

## 1.0.4 released 2013-07-17

Security Fixes

- When secret key was not explicitly set, it was not being used by the
  `csrf_hash()` function.  Thanks sparticvs for reporting.

Features

- The default 'CSRF check failed' page now offers a handy 'Try again' button,
  which resubmits the form.

Bug Fixes

- The fix for 1.0.3 inadvertantly turned off XMLHttpRequest
  overloading for all browsers; it has now been fixed to only
  apply to IE.

## 1.0.3 released 2012-01-31

Bug Fixes

- Internet Explorer 8 adds support for XMLHttpRequest.prototype,
  but this support is broken for method overloading.  We
  explicitly disable JavaScript overloading for Internet Explorer.
  Thanks Kelly Lu for reporting. <lubird@gmail.com>

- A global declaration was omitted, resulting in a variable
  not being properly introduced in PHP 5.3.  Thanks Whitney Beck for
  reporting. <whitney.a.beck@gmail.com>

## 1.0.2 released 2009-03-08

Security Fixes

- Due to a typo, csrf-magic accidentally treated the secret key
  as always present.  This means that there was a possible CSRF
  attack against users without any cookies.  No attacks in the
  wild were known at the time of this release.  Thanks Jakub
  Vrána for reporting.

## 1.0.1 released 2008-11-02

### New Features

- Support for composite tokens; this also fixes a bug with using
  IP-based tokens for users with cookies disabled.

- Native support cookie tokens; use csrf_conf('cookie', $name) to
  specify the name of a cookie that the CSRF token should be
  placed in.  This is useful if you have a Squid cache, and need
  to configure it to ignore this token.

- Tips/tricks section in README.txt.

- There is now a two hour expiration time on all tokens.  This
  can be modified using csrf_conf('expires', $seconds).

- ClickJacking protection using an iframe breaker.  Disable with
  csrf_conf('frame-breaker', false).

Bug Fixes

- CsrfMagic.send() incorrectly submitted GET requests twice,
  once without the magic token and once with the token.  Reported
  by Kelly Lu <lubird@gmail.com>.
