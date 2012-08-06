=== WP-FFPC ===
Contributors: cadeyrn
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=8LZ66LGFLMKJW&lc=HU&item_name=Peter%20Molnar%20photographer%2fdeveloper&item_number=petermolnar%2dpaypal%2ddonation&currency_code=USD&bn=PP%2dDonationsBF%3acredit%2epng%3aNonHosted
Tags: cache, APC, memcached, full page cache
Requires at least: 3.0
Tested up to: 3.4.1
Stable tag: 0.4

Fast Full Page Cache, backend can be memcached or APC

== Description ==
WP-FFPC is a full page cache plugin for WordPress. It can use APC or a memcached server as backend. The naming stands for Fast Full Page Cache.

PHP has two extension for communication with a memcached server, named Memcache and Memcached. The plugin can utilize both.

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
NOTE: some features ( like pingback link in HTTP header ) will not be available with this solution! ( yet )

Some parts were based on [Hyper Cache](http://wordpress.org/extend/plugins/hyper-cache "Hyper Cache") plugin by Satollo (info@satollo.net).

== Installation ==
1. Upload contents of `wp-ffpc.zip` to the `/wp-content/plugins/` directory
2. Enable WordPress cache by adding `define('WP_CACHE',true);` in wp-config.php
3. Activate the plugin through the `Plugins` menu in WordPress (please use network wide activation if used in a WordPress Network)
4. Fine tune the settings in `Settings` -> `wp-ffpc` menu in WordPress. For WordPress Network, please visit the Network Admin panel, the options will be available at WP-FFPC menu entry.

== Frequently Asked Questions ==

= Known bugs =

1. '%3C' character on home page load
**Description**: When the page address is entered by hand, it gets redirected to `page.address/%3C`.
**Solution**: only occurs with memcached, the reason is yet unknown. The bug has emerged once for me as well, setting up everything and restarting the memcached server solved it.

2. random-like characters instead of page
***SOLVED, description below is outdated***
**Description**: when nginx is used with memcached, characters like `xœí}ksÛ8²èg»`  shows up instead of the page.
**Solution**: this is the zlib compression of the page text. If PHP uses Memcached (with the 'd' at the ending), the compression cannot be turned off (it should, but it does not) and nginx is unable to read out the entries.
Please use only the Memcache extension. You also need to select it on the settings site, this is because some hosts may provide both PHP extensions.


= How to install memcache PHP extension? =
You need to have PECL on your machine. If it's ready, type `pecl install memcache` as root.
Some additional libraries can also be needed, but that varies by linux distributions.

= How to use the plugin on Amazon Linux? =
You have to remove the default yum package, named `php-pecl-memcache` and install `Memcache` with PECL.

== Changelog ==

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
