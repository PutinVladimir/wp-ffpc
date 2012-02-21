=== WP-FFPC ===
Contributors: cadeyrn
Tags: cache, APC, memcached, full page cache
Requires at least: 3.0
Tested up to: 3.3.1
Stable tag: 0.2.1

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

(1) pingback hostname will always be generated from the accessed domain, otherwise speed would get highly compromised

(2) If used in WordPress Network, the configuration will only be available for network admins at the network admin panel, and will be system-wide and will be applied for every blog.

(3) nginx compatility means that if used with PHP Memcache (not Memcached!) extension, the created memcached entries can be read and served directly from nginx, making the cache insanely fast.
If used with APC or Memcached, this feature is not available (no APC module for nginx, compression incompatibility with Memcached), although, naturally, the cache modul is functional and working, but it will be done by PHP instead of nginx.
Short nginx example configuration is generated on the plugin settings page if Memcache is selected as cache type.

Some parts were based on [Hyper Cache](http://wordpress.org/extend/plugins/hyper-cache "Hyper Cache") plugin by Satollo (info@satollo.net).

== Installation ==
1. Upload contents of `wp-ffpc.zip` to the `/wp-content/plugins/` directory
2. Enable WordPress cache by adding `define('WP_CACHE',true);` in wp-config.php
3. Activate the plugin through the `Plugins` menu in WordPress (please use network wide activation if used in a WordPress Network)
4. Fine tune the settings in `Settings` -> `wp-ffpc` menu in WordPress. For WordPress Network, please visit the Network Admin panel, the options will be available at WP-FFPC menu entry.

== Frequently Asked Questions ==

== Upgrade Notice ==

== Changelog ==

= 0.2.1 =
2012.02.21

* bugfix, duplicated inclusion could emerge, fix added, thanks for Géza Kuti for reporting!


= 0.2 =
2012.02.19

* added APC compression option ( requires PHP ZLIB ). Useful is output pages are large. Compression is on lowest level, therefore size/CPU load is more or less optimal.


= 0.1 =
2012.02.16

* first public release
