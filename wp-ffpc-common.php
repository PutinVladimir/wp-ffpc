<?php
/**
 * part of WordPress plugin WP-FFPC
 */

/**
 * function to test if selected backend is available & alive
 */
global $wp_ffpc_backend;
global $wp_nmc_redirect;

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
function wp_ffpc_init( $type = false ) {
	global $wp_ffpc_config;
	$wp_ffpc_backend_status = false;

	$reg_backend = $type;
	// $type is to test an exact backend */
	if ( !$type )
		$type = $wp_ffpc_config['cache_type'];


	/* verify selected storage is available */
	switch ($type)
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
			if ($reg_backend)
				global $wp_ffpc_backend;
			$wp_ffpc_backend = new Memcache();
			$wp_ffpc_backend->addServer( $wp_ffpc_config['host'] , $wp_ffpc_config['port'] );
			$wp_ffpc_backend_status = $wp_ffpc_backend->getServerStatus( $wp_ffpc_config['host'] , $wp_ffpc_config['port'] );
			break;

		/* in case of Memcached */
		case 'memcached':
			/* Memcached class does not exist, Memcached extension is not available */
			if (!class_exists('Memcached'))
				return false;
			if ($reg_backend)
				global $wp_ffpc_backend;
			$wp_ffpc_backend = new Memcached();
			$wp_ffpc_backend->addServer( $wp_ffpc_config['host'] , $wp_ffpc_config['port'] );
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
				apc_delete ( $data );
			}
			else
			{
				apc_clear_cache('user');
				apc_clear_cache('system');
			}
			break;

		/* in case of Memcache */
		case 'memcache':
		case 'memcached':
			global $wp_ffpc_backend;
			if ( $post_only )
			{
				$wp_ffpc_backend->delete( $meta );
				$wp_ffpc_backend->delete( $data );
			}
			else
			{
				$wp_ffpc_backend->flush();
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

	switch ($wp_ffpc_config['cache_type'])
	{
		case 'apc':
			/* use apc_store to overwrite data is existed */
			if ( $compress )
				$data = gzdeflate ( $data , 1 );
			apc_store( $key , $data , $wp_ffpc_config['expire']);
			break;
		case 'memcache':
			global $wp_ffpc_backend;
			/* false to disable compression, vital for nginx */
			$wp_ffpc_backend->set ( $key, $data , false, $wp_ffpc_config['expire'] );
			break;
		case 'memcached':
			global $wp_ffpc_backend;
			$wp_ffpc_backend->set ( $key, $data , $wp_ffpc_config['expire'] );
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

	switch ($wp_ffpc_config['cache_type'])
	{
		case 'apc':
			$value = apc_fetch($key);
			if ( $uncompress )
				$value = gzinflate ( $value );
			return $value;
		case 'memcache':
		case 'memcached':
			global $wp_ffpc_backend;
			return $wp_ffpc_backend->get($key);
		default:
			return false;
	}
}
?>
