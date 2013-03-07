=== WP-FFPC ===
Contributors: cadeyrn
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=XU3DG7LLA76WC
Tags: cache, APC, memcache, memcached, page cache, full page cache, nginx
Requires at least: 3.0
Tested up to: 3.5.1
Stable tag: 0.5.1

Store WordPress pages in memcached and serve them with nginx - unbeatable speed!

== Description ==
WP-FFPC is a full page cache plugin for WordPress. Supports memcached server or APC as backend and both widely available PHP memcached modules, Memcache and Memcached as well.

= Features: =
* exclude possibilities: of home, feeds, archieves, pages, singles
* possibility to enable caching for logged-in users
* APC or memcached server storage
* 404 caching
* redirects caching
* Last Modified HTTP header compatibility with 304 responses
* shortlink HTTP header preservation
* pingback HTTP header preservation(1)
* fallback to no caching if any error or problem occurs
* syslog & debug settings for troubleshooting
* supports multiple memcached backends
* Wordpress Network compatible(2)
* nginx compatible(3)

(1) pingback hostname will always be generated from the accessed domain, otherwise speed would get highly compromised

(2) If enabled as network-wide plugin in a WordPress Network, the configuration will only be available for network admins at the network admin panel, will be system-wide and will be applied for every blog.

(3) nginx compatility means that if used with PHP Memcache or PHP Memcached extension, the created memcached entries can be read and served directly from nginx.
If used with APC, this feature is not available (no APC module for nginx).
Short nginx example configuration is generated on the plugin settings page, under `nginx` tab according to the settings of the plugin.
NOTE: some features ( most of additional HTTP headers for example, like pingback, shortlink, etc. ) will not be available with this solution! ( yet )

Parts are based on [Hyper Cache](http://wordpress.org/extend/plugins/hyper-cache "Hyper Cache") plugin by Satollo (info@satollo.net).

== Installation ==
1. Upload contents of `wp-ffpc.zip` to the `/wp-content/plugins/` directory
2. Enable WordPress cache by adding `define('WP_CACHE',true);` in wp-config.php
3. Activate the plugin through the `Plugins` menu in WordPress ( site or Network wide )
4. Check the settings in `Settings` ( site or Network Admin, depending on activation wideness ) -> `WP-FFPC` menu in WordPress.
5. Save the settings. THIS STEP IS MANDATORY: without saving the settings, there will be no activated caching!

== Frequently Asked Questions ==

= How to install memcache PHP extension? =
On most of the distributions, php5-memcached or php5-memcache is available as package.
You can use PECL alternatively: `pecl install memcached`.
It's recommended to use Memcached instead of Memcache.

= How to use the plugin on Amazon Linux? =
You have to remove the default yum package, named `php-pecl-memcache` and install `Memcache` or `Memcached` through PECL.

== Changelog ==

= 0.5.1 =
*2013.03.07*

* settings link for plugins page
* readme cleanup
* setting link URL repair & cleanup

= 0.5 =
*2013.03.06*
WARNING, MAJOR CHANGES!

* default values bug ( causing %3C bug ) really fixed by the help of Mark Costlow <cheeks@swcp.com>
* UI cleanup, new tabbed layout
* WP-FFPC options moved from global menu to under Settings in both Site and Network Admin interfaces
* added 'persistent' checkbox for memcached connections
* added support for multiple memcached servers, feature request from ivan.buttinoni ( ivanbuttinoni @ WordPress.org forum )
* case-sensitive string checks replaced with case-insensitives, contribution of Mark Costlow <cheeks@swcp.com>
* refactored settings saving mechanism
* additional syslog informations
* additional comments on the code
* lots of minor fixes and code cleanup
* donation link on the top

= 0.4.3 =
*2013.03.03*

* long-running %3C bug fixed by the help of Mark Costlow <cheeks@swcp.com>, many thanks for it. It was cause by a bad check in the default values set-up: is_numeric applies for string numbers as well, which was unknown to me, and cause some of the values to be 0 where they should have been something different.

= 0.4.2 =
*2012.12.07*

* added optional sync protocoll option: replace all http->https or https->http depending on request protocol
* binary mode is working correctly with memcached extension
* added warning message for memcache extension in binary mode

**KNOWN ISSUES**

There are major problems with the "memcache" driver, the source is yet unkown. The situation is that there's no response from the memcached server using this driver; please avoid using it!

= 0.4.1 =
*2012.08.16*

* storage key extended with scheme ( http; https; etc. ), the miss caused problems when https request server CSS and JS files via http.

= 0.4 =
*2012.08.06*

* tested against new WordPress versions
* added lines to "memcached" storage to be able to work with nginx as well
* added lines to "memcached" to use binary protocol ( tested with PHP Memcached version 2.0.1 )

**KNOWN ISSUES**

* "memcache" extension fails in binary mode; the reason is under investigation

= 0.3.2 =
*2012.02.27*

* apc_cache_info replaced with apc_sma_info, makes plugin faster

= 0.3 =
*2012.02.21*

* added syslog debug messages possibility
* bugfix: removed (accidently used) short_open_tags

= 0.2.3 =
*2012.02.21*

* nginx-sample.conf file added, nginx config is created from here

= 0.2.2 =
*2012.02.21*

* memcache types bugfix, reported in forum, thanks!

= 0.2.1 =
*2012.02.21*

* bugfix, duplicated inclusion could emerge, fix added, thanks for Géza Kuti for reporting!

= 0.2 =
*2012.02.19*

* added APC compression option ( requires PHP ZLIB ). Useful is output pages are large. Compression is on lowest level, therefore size/CPU load is more or less optimal.

= 0.1 =
*2012.02.16*

* first public release
