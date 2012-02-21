<?php
/**
 * part of WordPress plugin WP-FFPC
 */

/**
 * function to test if selected backend is available & alive
 */
global $wp_ffpc_backend;
global $wp_nmc_redirect;

/* wp ffpc prefic */
if (!defined('WP_FFPC_PARAM'))
	define ( 'WP_FFPC_PARAM' , 'wp-ffpc' );
/* log level */
define ('WP_FFPC_LOG_LEVEL' , LOG_INFO);
/* define log ending message */
define ('WP_FFPC_LOG_TYPE_MSG' , '; cache type: '. $wp_ffpc_config['cache_type'] );


/* safety rules if file has been already included */
if ( function_exists('wp_ffpc_init') || function_exists('wp_ffpc_clear') || function_exists('wp_ffpc_set') || function_exists('wp_ffpc_get') )
	return false;

/**
 * init and backend alive check function
 *
 * @param $type [optional] if set, alive will be tested against this
 * 		  if false, backend will be globally initiated
 * 		  when set, backend will not become global, just tested if alive
 */
function wp_ffpc_init( $wp_ffpc_config ) {
	global $wp_ffpc_backend;
	$wp_ffpc_backend_status = false;

	if ( empty ( $wp_ffpc_config ))
		global $wp_ffpc_config;

	/* verify selected storage is available */
	switch ( $wp_ffpc_config['cache_type'] )
	{
		/* in case of apc */
		case 'apc':
			/* verify apc functions exist, apc ext is loaded */
			if (!function_exists('apc_cache_info'))
				return false;
			/* verify apc is working */
			if ( !apc_cache_info() )
				return false;
			$wp_ffpc_backend_status = true;
			break;

		/* in case of Memcache */
		case 'memcache':
			/* Memcache class does not exist, Memcache extension is not available */
			if (!class_exists('Memcache'))
				return false;
			if ( $wp_ffpc_backend == NULL )
			{
				$wp_ffpc_backend = new Memcache();
				$wp_ffpc_backend->addServer( $wp_ffpc_config['host'] , $wp_ffpc_config['port'] );
			}
			$wp_ffpc_backend_status = $wp_ffpc_backend->getServerStatus( $wp_ffpc_config['host'] , $wp_ffpc_config['port'] );
			break;

		/* in case of Memcached */
		case 'memcached':
			/* Memcached class does not exist, Memcached extension is not available */
			if (!class_exists('Memcached'))
				return false;
			if ( $wp_ffpc_backend == NULL )
			{
				$wp_ffpc_backend = new Memcached();
				$wp_ffpc_backend->addServer( $wp_ffpc_config['host'] , $wp_ffpc_config['port'] );
			}
			$wp_ffpc_backend_status = array_key_exists( $wp_ffpc_config['host'] . ':' . $wp_ffpc_config['port'] , $wp_ffpc_backend->getStats() );
			break;

		/* cache type is invalid */
		default:
			return false;
	}
	return $wp_ffpc_backend_status;
}

/**
 * clear cache element or flush cache
 *
 * @param $post_id [optional] : if registered with invalidation hook, post_id will be passed
 */
function wp_ffpc_clear ( $post_id = false ) {
	global $wp_ffpc_config;
	global $post;

	$post_only = ( $post_id === 'system_flush' ) ? false : $wp_ffpc_config['invalidation_method'];

	/* post invalidation enabled */
	if ( $post_only )
	{
		$path = substr ( get_permalink($post_id) , 7 );
		if (empty($path))
			return false;
		$meta = $wp_ffpc_config['prefix-meta'] . $path;
		$data = $wp_ffpc_config['prefix-data'] . $path;
	}

	switch ($wp_ffpc_config['cache_type'])
	{
		/* in case of apc */
		case 'apc':
			if ( $post_only )
			{
				apc_delete ( $meta );
				if ($wp_ffpc_config['syslog'])
					syslog( WP_FFPC_LOG_LEVEL , WP_FFPC_PARAM . ' clearing key: "'. $meta . '"' . WP_FFPC_LOG_TYPE_MSG );
				apc_delete ( $data );
				if ($wp_ffpc_config['syslog'])
					syslog( WP_FFPC_LOG_LEVEL , WP_FFPC_PARAM . ' clearing key: "'. $data . '"' . WP_FFPC_LOG_TYPE_MSG );
			}
			else
			{
				apc_clear_cache('user');
				if ($wp_ffpc_config['syslog'])
					syslog( WP_FFPC_LOG_LEVEL , WP_FFPC_PARAM . ' flushing user cache' . WP_FFPC_LOG_TYPE_MSG );
				apc_clear_cache('system');
				if ($wp_ffpc_config['syslog'])
					syslog( WP_FFPC_LOG_LEVEL , WP_FFPC_PARAM . ' flushing system cache' . WP_FFPC_LOG_TYPE_MSG );
			}
			break;

		/* in case of Memcache */
		case 'memcache':
		case 'memcached':
			global $wp_ffpc_backend;
			if ( $post_only )
			{
				$wp_ffpc_backend->delete( $meta );
				if ($wp_ffpc_config['syslog'])
					syslog( WP_FFPC_LOG_LEVEL , WP_FFPC_PARAM . ' clearing key: "'. $meta . '"' . WP_FFPC_LOG_TYPE_MSG );
				$wp_ffpc_backend->delete( $data );
				if ($wp_ffpc_config['syslog'])
					syslog( WP_FFPC_LOG_LEVEL , WP_FFPC_PARAM . ' clearing key: "'. $data . '"' . WP_FFPC_LOG_TYPE_MSG );
			}
			else
			{
				$wp_ffpc_backend->flush();
				if ($wp_ffpc_config['syslog'])
					syslog( WP_FFPC_LOG_LEVEL , WP_FFPC_PARAM . ' flushing cache' . WP_FFPC_LOG_TYPE_MSG );
			}
			break;

		/* cache type is invalid */
		default:
			return false;
	}
	return true;
}

/**
 * sets a key-value pair in backend
 *
 * @param &$key		store key, passed by reference for speed
 * @param &$data	store value, passed by reference for speed
 *
 */
function wp_ffpc_set ( &$key, &$data, $compress = false ) {
	global $wp_ffpc_config;
	global $wp_ffpc_backend;

	/* syslog */
	if ($wp_ffpc_config['syslog'])
	{
		if ( @is_array( $data ) )
			$string = serialize($data);
		elseif ( @is_string( $data ))
			$string = $data;

		$size = strlen($string);
		syslog( WP_FFPC_LOG_LEVEL , WP_FFPC_PARAM . ' set key: "'. $key . '", size: '. $size . ' byte(s)' . WP_FFPC_LOG_TYPE_MSG );
	}

	switch ($wp_ffpc_config['cache_type'])
	{
		case 'apc':
			/* use apc_store to overwrite data is existed */
			if ( $compress )
				$data = gzdeflate ( $data , 1 );
			return apc_store( $key , $data , $wp_ffpc_config['expire']);
			break;
		case 'memcache':
			if ( $wp_ffpc_backend != NULL )
				/* false to disable compression, vital for nginx */
				$wp_ffpc_backend->set ( $key, $data , false, $wp_ffpc_config['expire'] );
			else
				return false;
			break;
		case 'memcached':
			if ( $wp_ffpc_backend != NULL )
				$wp_ffpc_backend->set ( $key, $data , $wp_ffpc_config['expire'] );
			else
				return false;
			break;
	}
}

/**
 * gets cached element by key
 *
 * @param &$key: key of needed cache element
 *
 */
function wp_ffpc_get( &$key , $uncompress = false ) {
	global $wp_ffpc_config;
	global $wp_ffpc_backend;

	/* syslog */
	if ($wp_ffpc_config['syslog'])
		syslog( WP_FFPC_LOG_LEVEL , WP_FFPC_PARAM . ' get key: "'.$key . '"' . WP_FFPC_LOG_TYPE_MSG );

	switch ($wp_ffpc_config['cache_type'])
	{
		case 'apc':
			$value = apc_fetch($key);
			if ( $uncompress )
				$value = gzinflate ( $value );
			return $value;
		case 'memcache':
		case 'memcached':
			if ( $wp_ffpc_backend != NULL )
				return $wp_ffpc_backend->get($key);
			else
				return false;
		default:
			return false;
	}
}
?>
