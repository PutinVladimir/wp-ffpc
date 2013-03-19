<?php
/**
 * advanced cache worker of WordPress plugin WP-FFPC
 */

/* check for WP cache enabled*/
if ( !WP_CACHE )
	return false;

/* check for config */
if (!isset($wp_ffpc_config))
	return false;

/* no cache for post request (comments, plugins and so on) */
if ($_SERVER["REQUEST_METHOD"] == 'POST')
	return false;

/**
 * Try to avoid enabling the cache if sessions are managed
 * with request parameters and a session is active
 */
if (defined('SID') && SID != '')
	return false;

/* request uri */
$wp_ffpc_uri = $_SERVER['REQUEST_URI'];

/* no cache for uri with query strings, things usually go bad that way */
if ( stripos($wp_ffpc_uri, '?') !== false)
	return false;

/* no cache for pages starting with /wp- like WP admin */
if (stripos($wp_ffpc_uri, '/wp-') !== false)
	return false;

/* no cache for robots.txt */
if ( stripos($wp_ffpc_uri, 'robots.txt') )
	return false;

/* multisite files can be too large for memcached */
if ( function_exists('is_multisite') && stripos($wp_ffpc_uri, '/files/') )
	if ( is_multisite() )
		return false;

/* check if config is network active: use network config */
if (!empty ( $wp_ffpc_config['network'] ) )
	$wp_ffpc_config = $wp_ffpc_config['network'];
/* check if config is active for site : use site config */
elseif ( !empty ( $wp_ffpc_config[ $_SERVER['HTTP_HOST'] ] ) )
	$wp_ffpc_config = $wp_ffpc_config[ $_SERVER['HTTP_HOST'] ];
/* plugin config not found :( */
else
	return false;

/* no cache for for logged in users normally, only if enabled */
if ( $wp_ffpc_config['cache_loggedin'] == 0 ) {
	foreach ($_COOKIE as $n=>$v) {
		// test cookie makes to cache not work!!!
		if ($n == 'wordpress_test_cookie') continue;
		// wp 2.5 and wp 2.3 have different cookie prefix, skip cache if a post password cookie is present, also
		if ( (substr($n, 0, 14) == 'wordpressuser_' || substr($n, 0, 10) == 'wordpress_' || substr($n, 0, 12) == 'wp-postpass_') && !$wp_ffpc_config['cache_loggedin'] ) {
			return false;
		}
	}
}

$wp_ffpc_redirect = null;
$wp_ffpc_backend = new WP_FFPC_Backend( $wp_ffpc_config );

if ( $wp_ffpc_backend->status() === false )
	return false;

$wp_ffpc_keys = array ( 'meta', 'data' );
$wp_ffpc_values = array();

foreach ( $wp_ffpc_keys as $key ) {
	$value = $wp_ffpc_backend->get ( $wp_ffpc_backend->key ( $key ) );

	if ( ! $value ) {
		wp_ffpc_start();
		return;
	}
	else {
		$wp_ffpc_values[ $key ] = $value;
	}
}

/* serve cache 404 status */
if ( $wp_ffpc_values['meta']['status'] == 404 ) {
	header("HTTP/1.1 404 Not Found");
	flush();
	die();
}

/* server redirect cache */
if ( $wp_ffpc_values['meta']['redirect'] ) {
	header('Location: ' . $wp_ffpc_values['meta']['redirect'] );
	flush();
	die();
}

/* page is already cached on client side (chrome likes to do this, anyway, it's quite efficient) */
if ( array_key_exists( "HTTP_IF_MODIFIED_SINCE" , $_SERVER ) && !empty( $wp_ffpc_values['meta']['lastmodified'] ) ) {
	$if_modified_since = strtotime(preg_replace('/;.*$/', '', $_SERVER["HTTP_IF_MODIFIED_SINCE"]));
	/* check is cache is still valid */
	if ( $if_modified_since >= $wp_ffpc_values['meta']['lastmodified'] ) {
		header("HTTP/1.0 304 Not Modified");
		flush();
		die();
	}
}

/* if we reach this point it means data was found & correct, serve it */
header('Content-Type: ' . $wp_ffpc_values['meta']['mime']);

/* don't allow browser caching of page */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0');
header('Pragma: no-cache');

/* expire at this very moment */
header('Expires: ' . gmdate("D, d M Y H:i:s", time() ) . " GMT");

/* if shortlinks were set */
if (!empty ( $wp_ffpc_values['meta']['shortlink'] ) )
	header( 'Link:<'. $wp_ffpc_values['meta']['shortlink'] .'>; rel=shortlink' );

/* if last modifications were set (for posts & pages) */
if ( !empty($wp_ffpc_values['meta']['lastmodified']) )
	header( 'Last-Modified: ' . gmdate("D, d M Y H:i:s", $wp_ffpc_values['meta']['lastmodified'] ). " GMT" );

/* pingback urls, if existx */
if ( !empty( $wp_ffpc_values['meta']['pingback'] ) )
	header( 'X-Pingback: ' . $wp_ffpc_values['meta']['pingback'] );

/* for debugging */
if ( $wp_ffpc_config['response_header'] )
	header( 'X-Cache-Engine: WP-FFPC with ' . $wp_ffpc_config['cache_type'] );

/* HTML data */
echo $wp_ffpc_values['data'];
flush();
die();

/**
 * starts caching function
 *
 */
function wp_ffpc_start( ) {
	/* start object "colleting" and pass it the the actual storer function  */
	ob_start('wp_ffpc_callback');
}

/**
 * callback function for WordPress redirect urls
 *
 */
function wp_ffpc_redirect_callback ($redirect_url, $requested_url) {
	global $wp_ffpc_redirect;
	$wp_ffpc_redirect = $redirect_url;
	return $redirect_url;
}

/**
 * write cache function, called when page generation ended
 */
function wp_ffpc_callback( $buffer ) {
	global $wp_ffpc_config;
	global $wp_ffpc_backend;
	global $wp_ffpc_redirect;

	/* no is_home = error */
	if (!function_exists('is_home'))
		return $buffer;

	/* no <body> close tag = not HTML, don't cache */
	if (stripos($buffer, '</body>') === false)
		return $buffer;

	/* reset meta to solve conflicts */
	$meta = array();

	/* trim unneeded whitespace from beginning / ending of buffer */
	$buffer = trim( $buffer );

	/* Can be a trackback or other things without a body.
	   We do not cache them, WP needs to get those calls. */
	if (strlen($buffer) == 0)
		return '';

	if ( is_home() )
		$meta['type'] = 'home';
	elseif (is_feed() )
		$meta['type'] = 'feed';
	elseif ( is_archive() )
		$meta['type'] = 'archive';
	elseif ( is_single() )
		$meta['type'] = 'single';
	elseif ( is_page() )
		$meta['type'] = 'page';
	else
		$meta['type'] = 'unknown';

	/* check if caching is disabled for page type */
	$nocache_key = 'nocache_'. $meta['type'];

	/* don't cache if prevented by rule, also, log it */
	if ( $wp_ffpc_config[ $nocache_key ] == 1 ) {
		return $buffer;
	}

	if ( is_404() )
		$meta['status'] = 404;

	/* redirect page */
	if ( $wp_ffpc_redirect != null)
		$meta['redirect'] =  $wp_ffpc_redirect;

	/* feed is xml, all others forced to be HTML */
	if ( is_feed() )
		$meta['mime'] = 'text/xml;charset=';
	else
		$meta['mime'] = 'text/html;charset=';

	/* set mimetype */
	$meta['mime'] = $meta['mime'] . $wp_ffpc_config['charset'];

	/* try if post is available
		if made with archieve, last listed post can make this go bad
	*/
	global $post;
	if ( !empty($post) && ( $meta['type'] == 'single' || $meta['type'] == 'page' ) && !empty ( $post->post_modified_gmt ) ) {
		/* get last modification data */
		$meta['lastmodified'] = strtotime ( $post->post_modified_gmt );

		/* get shortlink, if possible */
		if (function_exists('wp_get_shortlink')) {
			$shortlink = wp_get_shortlink( );
			if (!empty ( $shortlink ) )
				$meta['shortlink'] = $shortlink;
		}
	}

	/* store pingback url if pingbacks are enabled */
	if ( get_option ( 'default_ping_status' ) == 'open' )
		$meta['pingback'] = get_bloginfo('pingback_url');

	/* sync all http and https requests if enabled */
	if ( $config['sync_protocols'] == '1' )
	{
		if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' )
			$_SERVER['HTTPS'] = 'on';

		if ( isset($_SERVER['HTTPS']) && ( ( strtolower($_SERVER['HTTPS']) == 'on' )  || ( $_SERVER['HTTPS'] == '1' ) ) ) {
			$sync_from = 'http://' . $_SERVER['SERVER_NAME'];
			$sync_to = 'https://' . $_SERVER['SERVER_NAME'];
		}
		else {
			$sync_from = 'https://' . $_SERVER['SERVER_NAME'];
			$sync_to = 'http://' . $_SERVER['SERVER_NAME'];
		}

		$buffer = str_replace ( $sync_from, $sync_to, $buffer );
	}

	$wp_ffpc_backend->set ( $wp_ffpc_backend->key ( 'meta' ) , $meta );
	$wp_ffpc_backend->set ( $wp_ffpc_backend->key ( 'data' ) , $buffer );

	/* vital for nginx, make no problem at other places */
	header("HTTP/1.1 200 OK");

	/* echoes HTML out */
	return $buffer;
}

?>
