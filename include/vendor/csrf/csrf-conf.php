<?php
// CONFIGURATION:

/**
 * Convenience parameter for disabling all of our functionality;
 * equivalent to setting 'rewrite' to false and 'defer' to true.
 */
$GLOBALS['csrf']['disable'] = false;

/**
 * By default, when you include this file csrf-magic will automatically check
 * and exit if the CSRF token is invalid. This will defer executing
 * csrf_check() until you're ready.  You can also pass false as a parameter to
 * that function, in which case the function will not exit but instead return
 * a boolean false if the CSRF check failed. This allows for tighter integration
 * with your system.
 */
$GLOBALS['csrf']['defer'] = false;

/**
 * This is the amount of seconds you wish to allow before any token becomes
 * invalid; the default is two hours, which should be more than enough for
 * most websites.
 */
$GLOBALS['csrf']['expires'] = 7200;

/**
 * Callback function to execute when there's the CSRF check fails and
 * $fatal == true (see csrf_check). This will usually output an error message
 * about the failure.
 */
$GLOBALS['csrf']['callback'] = 'csrf_callback';

/**
 * Whether or not to include our JavaScript library which also rewrites
 * AJAX requests on this domain. Set this to the web path. This setting only works
 * with supported JavaScript libraries in Internet Explorer; see README.txt for
 * a list of supported libraries.
 */
$GLOBALS['csrf']['rewrite-js'] = false;

/**
 * A secret key used when hashing items. Please generate a random string and
 * place it here. If you change this value, all previously generated tokens
 * will become invalid.
 */
$GLOBALS['csrf']['secret'] = '';
// nota bene: library code should use csrf_get_secret() and not access
// this global directly

/**
 * Set this to false to disable csrf-magic's output handler, and therefore,
 * its rewriting capabilities. If you're serving non HTML content, you should
 * definitely set this false.
 */
$GLOBALS['csrf']['rewrite'] = true;

/**
 * Whether or not to use IP addresses when binding a user to a token. This is
 * less reliable and less secure than sessions, but is useful when you need
 * to give facilities to anonymous users and do not wish to maintain a database
 * of valid keys.
 */
$GLOBALS['csrf']['allow-ip'] = true;

/**
 * If this information is available, use the cookie by this name to determine
 * whether or not to allow the request. This is a shortcut implementation
 * very similar to 'key', but we randomly set the cookie ourselves.
 */
$GLOBALS['csrf']['cookie'] = '__csrf_cookie';

/**
 * If this information is available, set this to a unique identifier (it
 * can be an integer or a unique username) for the current "user" of this
 * application. The token will then be globally valid for all of that user's
 * operations, but no one else. This requires that 'secret' be set.
 */
$GLOBALS['csrf']['user'] = false;

/**
 * This is an arbitrary secret value associated with the user's session. This
 * will most probably be the contents of a cookie, as an attacker cannot easily
 * determine this information. Warning: If the attacker knows this value, they
 * can easily spoof a token. This is a generic implementation; sessions should
 * work in most cases.
 *
 * Why would you want to use this? Lets suppose you have a squid cache for your
 * website, and the presence of a session cookie bypasses it. Let's also say
 * you allow anonymous users to interact with the website; submitting forms
 * and AJAX. Previously, you didn't have any CSRF protection for anonymous users
 * and so they never got sessions; you don't want to start using sessions either,
 * otherwise you'll bypass the Squid cache. Setup a different cookie for CSRF
 * tokens, and have Squid ignore that cookie for get requests, for anonymous
 * users. (If you haven't guessed, this scheme was(?) used for MediaWiki).
 */
$GLOBALS['csrf']['key'] = false;

/**
 * The name of the magic CSRF token that will be placed in all forms, i.e.
 * the contents of <input type="hidden" name="$name" value="CSRF-TOKEN" />
 */
$GLOBALS['csrf']['input-name'] = '__csrf_magic';

/**
 * Set this to false if your site must work inside of frame/iframe elements,
 * but do so at your own risk: this configuration protects you against CSS
 * overlay attacks that defeat tokens.
 */
$GLOBALS['csrf']['frame-breaker'] = true;

/**
 * Whether or not CSRF Magic should prefer using the session id as the
 * primary method of generating a secured token for validating the post
 * data
 */
$GLOBALS['csrf']['session'] = true;

/**
 * Whether or not CSRF Magic should be allowed to start a new session in order
 * to determine the key.
 */
$GLOBALS['csrf']['auto-session'] = true;

/**
 * Whether or not csrf-magic should produce XHTML style tags.
 */
$GLOBALS['csrf']['xhtml'] = true;

// FUNCTIONS:

// Don't edit this!
$GLOBALS['csrf']['version'] = '1.1.0';

/**
 * Where to log output to if we need to
 */
$GLOBALS['csrf']['log_file'] = '';

/**
 * Whether to echo logging to the secreen
 */
$GLOBALS['csrf']['log_echo'] = '';

/**
 * Path to secret file
 */
$GLOBALS['csrf']['path_secret'] = '';

/**
 * Startup function, normally called csrf_startup()
 */
$GLOBALS['csrf']['startup_func'] = '';

/**
 * Hashing function to use, defaults to sha1
 */
$GLOBALS['csrf']['hash'] = '';

/**
 * Path to base website
 */
$GLOBALS['csrf']['url_path'] = '';