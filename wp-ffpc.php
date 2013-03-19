<?php
/*
Plugin Name: WP-FFPC
Plugin URI: http://petermolnar.eu/wordpress/wp-ffpc
Description: WordPress cache plugin for memcached & nginx - unbeatable speed
Version: 1.0
Author: Peter Molnar <hello@petermolnar.eu>
Author URI: http://petermolnar.eu/
License: GPLv3
*/

/*  Copyright 2010-2013 Peter Molnar ( hello@petermolnar.eu )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 3, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

include_once ( 'wp-ffpc-class.php' );

$wp_ffpc_defaults = array (
	'hosts'=>'127.0.0.1:11211',
	'expire'=>300,
	'invalidation_method'=>0,
	'prefix_meta' =>'meta-',
	'prefix_data' =>'data-',
	'charset' => 'utf-8',
	'log_info' => false,
	'log' => true,
	'cache_type' => 'memcached',
	'cache_loggedin' => false,
	'nocache_home' => false,
	'nocache_feed' => false,
	'nocache_archive' => false,
	'nocache_single' => false,
	'nocache_page' => false,
	'sync_protocols' => false,
	'persistent' => false,
	'response_header' => false,
);

$wp_ffpc = new WP_FFPC ( 'wp-ffpc', '1.0', 'WP-FFPC', $wp_ffpc_defaults, 'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=XU3DG7LLA76WC' );


?>
