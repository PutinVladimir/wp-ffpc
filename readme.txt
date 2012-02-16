=== WP-FFPC ===
Contributors: cadeyrn
Tags: cache, APC, memcached, full page cache
Requires at least: 2.7
Tested up to: 3.3
Stable tag: 0.1

Fast Full Page Cache, backend can be memcached or APC


== Description ==
WP-FFPC is a full page cache plugin for WordPress. It can use APC or a memcached server as backend.
PHP has two extension for communication with a memcached server, named Memcache and Memcached. The plugin can utilize both.
A special function is that when used with the Memcache extension it produces memcached entries that can be read and served directly from nginx, making the cache insanely fast.

The naming stands for Fast Full Page Cache.

If used in WordPress Network, the configuration will only be available for network admins at the network admin panel, and will be system-wide and will be applied for every blog.

Some parts were based on Hyper Cache plugin by Satollo (info@satollo.net).


= Usage =
Configure the plugin at the admin section

== Installation ==
1. Upload contents of `wp-ffpc.zip` to the `/wp-content/plugins/` directory
2. Activate the plugin through the `Plugins` menu in WordPress (please use network wide activation if used in a WordPress Network)
3. Fine tune the settings in `Settings` -> `wp-ffpc` menu in WordPress. For WordPress Network, please visit the Network Admin panel, the options will be available at WP-FFPC menu entry.

== Frequently Asked Questions ==


== Screenshots ==


== Upgrade Notice ==


== Changelog ==

= 0.1 =
2012.02.16

* first public release
