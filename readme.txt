=== WP-FFPC ===
Contributors: cadeyrn
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=XU3DG7LLA76WC
Tags: cache, APC, memcached, full page cache
Requires at least: 3.0
Tested up to: 3.5.1
Stable tag: 0.4.3

Fast Full Page Cache, backend can be memcached or APC

== Description ==
WP-FFPC is a full page cache plugin for WordPress. It can use APC or a memcached server as backend.
The naming stands for Fast Full Page Cache.

PHP has two extension for communication with a memcached server, named Memcache and Memcached. The plugin can utilize both, however, the recommended is memcached.

= Features: =
* exclude possibility of home, feeds, archieves, pages, singles
* use APC or memcached as backend
* 404 caching
* redirects caching
* Last Modified HTTP header compatibility with 304 responses
* shortlink HTTP header preservation
* pingback HTTP header preservation(1)
* fallback to no caching if any error or problem occurs
* Wordpress Network compatible(2)
* nginx compatible(3)
* (optional) syslog messages of sets-gets-flushes

(1) pingback hostname will always be generated from the accessed domain, otherwise speed would get highly compromised

(2) If used in WordPress Network, the configuration will only be available for network admins at the network admin panel, and will be system-wide and will be applied for every blog.

(3) nginx compatility means that if used with PHP Memcache or PHP Memcached extension, the created memcached entries can be read and served directly from nginx, making the cache insanely fast.
If used with APC, this feature is not available (no APC module for nginx), although, naturally, the cache modul is functional and working, but it will be done by PHP instead of nginx.
Short nginx example configuration is generated on the plugin settings page if Memcache or Memcached is selected as cache type.
NOTE: some features ( most of additional HTTP headers for example ) will not be available with this solution! ( yet )

Some parts were based on [Hyper Cache](http://wordpress.org/extend/plugins/hyper-cache "Hyper Cache") plugin by Satollo (info@satollo.net).

== Installation ==
1. Upload contents of `wp-ffpc.zip` to the `/wp-content/plugins/` directory
2. Enable WordPress cache by adding `define('WP_CACHE',true);` in wp-config.php
3. Activate the plugin through the `Plugins` menu in WordPress (please use network wide activation if used in a WordPress Network)
4. Fine tune the settings in `Settings` -> `wp-ffpc` menu in WordPress.
For WordPress Network, please visit the Network Admin panel, the options will be available under Network Admin Settings page, in WP-FFPC menu entry.

== Frequently Asked Questions ==

= How to install memcache PHP extension? =
On most of the distributions, php5-memcached or php5-mecache is available as package.
You can use PECL alternatively: `pecl install memcached`.

= How to use the plugin on Amazon Linux? =
You have to remove the default yum package, named `php-pecl-memcache` and install `Memcache` with PECL.

== Changelog ==

= 0.5 =
2013.03.04

WARNING, MAJOR CHANGES!

* long-running %3C really fixed ( version 0.4.3 was dead end ) by the help of Mark Costlow <cheeks@swcp.com>
* UI cleanup, introducing tabbed interface
* WP-FFPC options moved from global menu to under Settings in both Site and Network Admin interfaces
* added 'persistent' checkbox for memcached connections
* added possibility to add multiple memcached servers
* case-sensitive string checks replaced with case-insensitives, contribution of Mark Costlow <cheeks@swcp.com>
* refactored settings saving mechanism
* additional syslog informations

= 0.4.3 =
2013.03.03

* long-running %3C bug fixed by the help of Mark Costlow <cheeks@swcp.com>, many thanks for it. It was cause by a bad check in the default values set-up: is_numeric applies for string numbers as well, which was unknown to me, and cause some of the values to be 0 where they should have been something different.

= 0.4.2 =
2012.12.07

* added optional sync protocoll option: replace all http->https or https->http depending on request protocol
* binary mode is working correctly with memcached extension
* added warning message for memcache extension in binary mode

KNOWN ISSUES

There are major problems with the "memcache" driver, the source is yet unkown. The situation is that there's no response from the memcached server using this driver; please avoid using it!

= 0.4.1 =
2012.08.16

* storage key extended with scheme ( http; https; etc. ), the miss caused problems when https request server CSS and JS files via http.

= 0.4 =
2012.08.06

* tested against new WordPress versions
* added lines to "memcached" storage to be able to work with nginx as well
* added lines to "memcached" to use binary protocol ( tested with PHP Memcached version 2.0.1 )

KNOWN ISSUES

* "memcache" extension fails in binary mode; the reason is under investigation

= 0.3.2 =
2012.02.27

* apc_cache_info replaced with apc_sma_info, makes plugin faster

= 0.3 =
2012.02.21

* added syslog debug messages possibility
* bugfix: removed (accidently used) short_open_tags

= 0.2.3 =
2012.02.21

* nginx-sample.conf file added, nginx config is created from here

= 0.2.2 =
2012.02.21

* memcache types bugfix, reported in forum, thanks!

= 0.2.1 =
2012.02.21

* bugfix, duplicated inclusion could emerge, fix added, thanks for Géza Kuti for reporting!

= 0.2 =
2012.02.19

* added APC compression option ( requires PHP ZLIB ). Useful is output pages are large. Compression is on lowest level, therefore size/CPU load is more or less optimal.

= 0.1 =
2012.02.16

* first public release
