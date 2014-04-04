=== WP-FFPC ===
Contributors: cadeyrn
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=XU3DG7LLA76WC
Tags: cache, page cache, full page cache, nginx, memcached, apc, speed
Requires at least: 3.0
Tested up to: 3.9
Stable tag: 1.3
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Fastest way of cache for WordPress: memcached + nginx!

== Description ==
WP-FFPC ( WordPress Fast Full Page Cache ) is a cache plugin for [WordPress](http://wordpress.org/ "WordPress"). It works with any webserver, including apache2, lighttpd, nginx, however, can be connected with [NGiNX](http://NGiNX.org "NGiNX") through memcached for unbeatable speed.
Supports PHP Memcached, PHP Memcache and APC as storage engines, subdomain and domain based WordPress Networks.

= Features: =
* Wordpress Network support ( for subdomain layout )
* cache exclude possibilities ( home, feeds, archieves, pages, singles )
* (optional) cache for logged-in users
* 404 caching
* canonical redirects caching
* Last Modified HTTP header support ( for 304 responses )
* shortlink HTTP header preservation
* pingback HTTP header preservation
* talkative log for [WP_DEBUG](http://codex.wordpress.org/WP_DEBUG "WP_DEBUG")
* multiple memcached upstream support
* precache ( manually or by timed by wp-cron )
* [NGiNX](http://NGiNX.org "NGiNX") compatibility

Many thanks for contributors, supporters, testers & bug reporters:

* [Harold Kyle](https://github.com/haroldkyle "Harold Kyle")
* [Eric Gilette](http://www.ericgillette.com/ "Eric Gilette")
* [doconeill](http://wordpress.org/support/profile/doconeill "doconeill")
* [Mark Costlow](mailto:cheeks@swcp.com "Mark Costlow")
* [Jason Miller](mailto:jason@redconfetti.com "Jason Miller")
* [Dave Clark](https://github.com/dkcwd "Dave Clark")

== Installation ==

1. Upload contents of `wp-ffpc.zip` to the `/wp-content/plugins/` directory
2. Enable WordPress cache by adding `define('WP_CACHE',true);` in wp-config.php
3. Activate the plugin through the `Plugins` menu in WordPress ( site or Network wide )
4. Check the settings in `Settings` ( site or Network Admin, depending on activation wideness ) -> `WP-FFPC` menu in WordPress.
5. Save the settings. THIS STEP IS MANDATORY: without saving the settings, there will be no activated caching!

= Using the plugin with NGiNX =
If the storage engine is either PHP Memcache or PHP Memcached extension, the created entries can be read and served directly from NGiNX ( if it has memcache or memc extension )
A short configuration example is generated on the plugin settings page, under `NGiNX` tab according to the saved settings.
**NOTE:** Some features ( most of additional HTTP headers for example, like pingback, shortlink, etc. ) will not be available with this solution.

== Frequently Asked Questions ==

= How to use the plugin on Amazon Linux? =
You have to remove the default yum package, named `php-pecl-memcache` and install `Memcached` through PECL.

= How to use the plugin in a WordPress Network =
From version 1.0, the plugin supports subdomain based WordPress Network with possible different per site cache settings. If the plugin is network active, obviously the network wide settings will be used for all of the sites. If it's activated only on some of the sites, the other will not be affected and even the cache storage backend can be different from site to site.

= What are the plugin's requirements? =

* WordPress >= 3.0

and **at least one** of the following for storage backend:

* PHP APC
* PHP Memcached > 0.1.0
* PHP Memcache > 2.1.0

= How logging works in the plugin? =
Log levels by default ( if logging enabled ) includes warning and error level standard PHP messages.
Additional info level log is available when [WP_DEBUG](http://codex.wordpress.org/WP_DEBUG "WP_DEBUG") is enabled.

= How can I contribute? =
In order to make contributions a lot easier, I've moved the plugin development to [GitHub](https://github.com/petermolnar/wp-ffpc "GitHub"), feel free to fork and put shiny, new things in it and get in touch with me [hello@petermolnar.eu](mailto:hello@petermolnar.eu "hello@petermolnar.eu") when you have it ready.

= Where can I turn for support? =
I provide support for the plugin as best as I can, but it comes without guarantee.
Please post feature requests to [WP-FFPC feature request topic](http://wordpress.org/support/topic/feature-requests-14 "WP-FFPC feature request topic") and any questions on the forum.

== Screenshots ==

1. settings screen, cache type and basic settings
2. debug and in depth-options
3. cache exceptions
4. memcached servers settings
5. NGiNX example

== Changelog ==

= 1.3 =
*2014-04-04*

What's fixed:

* uninstall will not fail anymore ( and I hate PHP for it's retarted language restrictions )
* typo fix for memcache functions from [Dave Clark](https://github.com/dkcwd "Dave Clark")
* uninstall security lines from [Dave Clark](https://github.com/dkcwd "Dave Clark")
* modification to nginx sample file from [Harold Kyle](https://github.com/haroldkyle "Harold Kyle") to skip all urls with query string present

What's new:

* added unix socket memcache module support ( ONLY for memcache backend for now )

= 1.2.2 =
*2013-11-07*

What's fixed:

* 404 for first hit of 404 pages; bug report from [phoenix13](wordpress.org/support/profile/phoenix13 phoenix13)

= 1.2.1 =
*2013-07-23*

What's fixed:

* call to undefined function get_blog_option error fixed

= 1.2 =
*2013-07-17*

What's new:

* additional cookie patterns to exclude visitors from cache, contribution from [Harold Kyle](https://github.com/haroldkyle "Harold Kyle")
* syslog dropped; using "regular" PHP log instead
* pre-cache from wp-cron
* changeable key scheme ( was fixed previously ); possibility to add user-specific cache if PHPESSID cookie is present

What's fixed:

* logged in cookie check fixed ( was not checking all WordPress cookies )
* global error messages to show if settings are not saved

**Dropped functionalities**

* there's no info log on/off anymore, it's triggered when WP_DEBUG is active
* sync protocols has been removed for two reasons: this has to be done by other systems and causes issues in special cases

**For Devs**

* the abstract class have been moved into a separate Github repository, [wp-common](https://github.com/petermolnar/wp-common "wp-common"). Because PHP is not capable of replacing/redefining classes, there's a versioning with the abstract and the utilities class, please be aware of this.

= 1.1.1 =
*2013-04-25*

* bugfix: Memcache plugin was diplaying server status incorrectly ( although the plugin was working )
* bugfix: typo prevented log to work correctly

= 1.1 =
*2013-04-24*

What's new:

* HTML comment option for displaying cache info before closing "body" tag ( a.k.a make sure it works "noob" method )
* pre-cache function ( only manual pre-cache is enabled for now; uses permalinks structure )
* new, additional invalidation method: clear post & all taxonomy cache, including feeds
* full virtual server example to use the plugin with nginx ( originally it was only a snippet required to use the plugin )

What's fixed:

* contributed fixes from [Harold Kyle](https://github.com/haroldkyle "Harold Kyle") to surpress PHP notices and warnings; better CSS & JS enqueue; corrected admin panel descriptions
* bugfix for status check ( there were situations where the status was not updated correctly )
* manual flush cache bug fixed ( was only flushing if the settings were on "flush all" )
* bugfix on data & meta prefixes ( some places used hardcoded prefixes )
* feed caching fixed ( due to a security check it turned out feeds were excluded for a long time )

= 1.0 =
*2013-03-22*

* plugin development using [GitHub repository](https://github.com/petermolnar/wp-ffpc "GitHub repository") from this version
* Software licence change from GPLv2 to GPLv3
* backend code completely replaced ( object-based backend, improved readability & better structure, lot less global vars, etc. )
* added proper uninstall ( uninstall hook was not removing options from DB, uninstall.php will )
* revisited multisite support ( eliminated overwriting-problems )
* preparations for localization support ( all strings are now go through WordPress translate if available )
* more detailed log & error messages
* retouched Memcache initialization ( faster connect, cleaner persistent connections )
* proper settings migration from previous versions

**Bugfixes**

* faulty expiration times fixed
* eliminated warning message for memcache when no memcache extension is present
* fixed multisite settings overwriting issue

**Dropped functions**

* APC entry compression support

= 0.6.1 =
*2013-03-08*

* refactored & corrected backend status check for memcached driver

= 0.6 =
*2013-03-08*

* true WordPress Network support:
  * if enabled network-wide, settings will be the same for every site
  * if enabled only per site settings could vary from site to site and cache could be active or disabled on a per site basis without interfering other sites
* delete options button to help solving problems

= 0.5.1 =
*2013-03-07*

* settings link for plugins page
* readme cleanup
* setting link URL repair & cleanup

= 0.5 =
*2013-03-06*
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
*2013-03-03*

* long-running %3C bug fixed by the help of Mark Costlow <cheeks@swcp.com>, many thanks for it. It was cause by a bad check in the default values set-up: is_numeric applies for string numbers as well, which was unknown to me, and cause some of the values to be 0 where they should have been something different.

= 0.4.2 =
*2012-12-07*

* added optional sync protocoll option: replace all http->https or https->http depending on request protocol
* binary mode is working correctly with memcached extension
* added warning message for memcache extension in binary mode

**KNOWN ISSUES**

There are major problems with the "memcache" driver, the source is yet unkown. The situation is that there's no response from the memcached server using this driver; please avoid using it!

= 0.4.1 =
*2012-08-16*

* storage key extended with scheme ( http; https; etc. ), the miss caused problems when https request server CSS and JS files via http.

= 0.4 =
*2012-08-06*

* tested against new WordPress versions
* added lines to "memcached" storage to be able to work with NGiNX as well
* added lines to "memcached" to use binary protocol ( tested with PHP Memcached version 2.0.1 )

**KNOWN ISSUES**

* "memcache" extension fails in binary mode; the reason is under investigation

= 0.3.2 =
*2012-02-27*

* apc_cache_info replaced with apc_sma_info, makes plugin faster

= 0.3 =
*2012-02-21*

* added syslog debug messages possibility
* bugfix: removed (accidently used) short_open_tags

= 0.2.3 =
*2012-02-21*

* NGiNX-sample.conf file added, NGiNX config is created from here

= 0.2.2 =
*2012-02-21*

* memcache types bugfix, reported in forum, thanks!

= 0.2.1 =
*2012-02-21*

* bugfix, duplicated inclusion could emerge, fix added, thanks for Géza Kuti for reporting!

= 0.2 =
*2012-02-19*

* added APC compression option ( requires PHP ZLIB ). Useful is output pages are large. Compression is on lowest level, therefore size/CPU load is more or less optimal.

= 0.1 =
*2012-02-16*

* first public release
