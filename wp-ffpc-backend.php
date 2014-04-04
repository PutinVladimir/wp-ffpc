<?php
/**
 * backend driver for WordPress plugin WP-FFPC
 *
 * supported storages:
 *  - APC
 *  - Memcached
 *  - Memcache
 *
 */

if (!class_exists('WP_FFPC_Backend')) {

	/* get the plugin abstract class*/
	include_once ( 'wp-common/wp-plugin-utilities.php');


	/* __ only availabe if we're running from the inside of wordpress, not in advanced-cache.php phase */
	if ( function_exists ( '__' ) ) {
		function __translate__ ( $text, $domain ) { return __($text, $domain); }
	}
	else {
		function __translate__ ( $text, $domain ) { return $text; }
	}

	/**
	 *
	 * @var string	$plugin_constant	Namespace of the plugin
	 * @var mixed	$connection	Backend object storage variable
	 * @var boolean	$alive		Alive flag of backend connection
	 * @var boolean $network	WordPress Network flag
	 * @var array	$options	Configuration settings array
	 * @var array	$status		Backends status storage
	 * @var array	$cookies	Logged in cookies to search for
	 * @var array	$urimap		Map to render key with
	 * @var object	$utilities	Utilities singleton
	 *
	 */
	class WP_FFPC_Backend {

		const network_key = 'network';
		const host_separator  = ',';
		const port_separator  = ':';

		private $plugin_constant = 'wp-ffpc';
		private $connection = NULL;
		private $alive = false;
		private $network = false;
		private $options = array();
		private $status = array();
		public $cookies = array();
		private $urimap = array();
		private $utilities;

		/**
		* constructor
		*
		* @param mixed $config Configuration options
		* @param boolean $network WordPress Network indicator flah
		*
		*/
		public function __construct( $config, $network = false ) {

			/* no config, nothing is going to work */
			if ( empty ( $config ) ) {
				return false;
				//die ( __translate__ ( 'WP-FFPC Backend class received empty configuration array, the plugin will not work this way', $this->plugin_constant ) );
			}

			/* set config */
			$this->options = $config;

			/* set network flag */
			$this->network = $network;

			/* these are the list of the cookies to look for when looking for logged in user */
			$this->cookies = array ( 'comment_author_' , 'wordpressuser_' , 'wp-postpass_', 'wordpress_logged_in_' );

			/* make utilities singleton */
			$this->utilities = WP_Plugins_Utilities_v1::Utility();

			/* map the key with the predefined schemes */
			$ruser = isset ( $_SERVER['REMOTE_USER'] ) ? $_SERVER['REMOTE_USER'] : '';
			$ruri = isset ( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
			$rhost = isset ( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '';
			$scookie = isset ( $_COOKIE['PHPSESSID'] ) ? $_COOKIE['PHPSESSID'] : '';

			$this->urimap = array(
				'$scheme' => str_replace ( '://', '', $this->utilities->replace_if_ssl ( 'http://' ) ),
				'$host' => $rhost,
				'$request_uri' => $ruri,
				'$remote_user' => $ruser,
				'$cookie_PHPSESSID' => $scookie,
			);

			/* split hosts entry to servers */
			$this->set_servers();

			/* call backend initiator based on cache type */
			$init = $this->proxy( 'init' );

			/* info level */
			$this->log (  __translate__('init starting', $this->plugin_constant ));
			$this->$init();

		}

		/*********************** PUBLIC / PROXY FUNCTIONS ***********************/

		/**
		 * build key to make requests with
		 *
		 * @param string $prefix prefix to add to prefix
		 *
		 */
		public function key ( &$prefix ) {
			/* data is string only with content, meta is not used in nginx */
			$key = $prefix . str_replace ( array_keys( $this->urimap ), $this->urimap, $this->options['key'] );
			$this->log (  __translate__('original key configuration: ', $this->plugin_constant ) . $this->options['key'] );
			$this->log (  __translate__('setting key to: ', $this->plugin_constant ) . $key );

			return $key;
		}


		/**
		 * public get function, transparent proxy to internal function based on backend
		 *
		 * @param string $key Cache key to get value for
		 *
		 * @return mixed False when entry not found or entry value on success
		 */
		public function get ( &$key ) {

			/* look for backend aliveness, exit on inactive backend */
			if ( ! $this->is_alive() )
				return false;

			/* log the current action */
			$this->log (  __translate__('get ', $this->plugin_constant ). $key );

			/* proxy to internal function */
			$internal = $this->proxy( 'get' );
			$result = $this->$internal( $key );

			if ( $result === false  )
				$this->log (  __translate__( "failed to get entry: ", $this->plugin_constant ) . $key );

			return $result;
		}

		/**
		 * public set function, transparent proxy to internal function based on backend
		 *
		 * @param string $key Cache key to set with ( reference only, for speed )
		 * @param mixed $data Data to set ( reference only, for speed )
		 *
		 * @return mixed $result status of set function
		 */
		public function set ( &$key, &$data ) {

			/* look for backend aliveness, exit on inactive backend */
			if ( ! $this->is_alive() )
				return false;

			/* log the current action */
			$this->log( __translate__('set ', $this->plugin_constant ) . $key . __translate__(' expiration time: ', $this->plugin_constant ) . $this->options['expire']);

			/* proxy to internal function */
			$internal = $this->options['cache_type'] . '_set';
			$result = $this->$internal( $key, $data );

			/* check result validity */
			if ( $result === false )
				$this->log (  __translate__('failed to set entry: ', $this->plugin_constant ) . $key, LOG_WARNING );

			return $result;
		}

		/**
		 * public get function, transparent proxy to internal function based on backend
		 *
		 * @param string $post_id	ID of post to invalidate
		 * @param boolean $force 	Force flush cache
		 *
		 */
		public function clear ( $post_id = false, $force = false ) {

			/* look for backend aliveness, exit on inactive backend */
			if ( ! $this->is_alive() )
				return false;

			/* exit if no post_id is specified */
			if ( empty ( $post_id ) && $force === false ) {
				$this->log (  __translate__('not clearing unidentified post ', $this->plugin_constant ), LOG_WARNING );
				return false;
			}

			/* if invalidation method is set to full, flush cache */
			if ( $this->options['invalidation_method'] === 0 || $force === true ) {
				/* log action */
				$this->log (  __translate__('flushing cache', $this->plugin_constant ) );

				/* proxy to internal function */
				$internal = $this->proxy ( 'flush' );
				$result = $this->$internal();

				if ( $result === false )
					$this->log (  __translate__('failed to flush cache', $this->plugin_constant ), LOG_WARNING );

				return $result;
			}

			/* storage for entries to clear */
			$to_clear = array();

			/* clear taxonomies if settings requires it */
			if ( $this->options['invalidation_method'] == 2 ) {
				/* this will only clear the current blog's entries */
				$this->taxonomy_links( $to_clear );
			}

			/* if there's a post id pushed, it needs to be invalidated in all cases */
			if ( !empty ( $post_id ) ) {

				/* need permalink functions */
				if ( !function_exists('get_permalink') )
					include_once ( ABSPATH . 'wp-includes/link-template.php' );

				/* get path from permalink */
				$path = substr ( get_permalink( $post_id ) , 7 );

				/* no path, don't do anything */
				if ( empty( $path ) ) {
					$this->log (  __translate__('unable to determine path from Post Permalink, post ID: ', $this->plugin_constant ) . $post_id , LOG_WARNING );
					return false;
				}

				if ( isset($_SERVER['HTTPS']) && ( ( strtolower($_SERVER['HTTPS']) == 'on' )  || ( $_SERVER['HTTPS'] == '1' ) ) )
					$protocol = 'https://';
				else
					$protocol = 'http://';

				/* elements to clear
				   values are keys, faster to process and eliminates duplicates
				*/
				$to_clear[ $protocol . $path ] = true;
			}

			foreach ( $to_clear as $link => $dummy ) {
				/* clear all feeds as well */
				$to_clear[ $link. 'feed' ] = true;
			}

			/* add data & meta prefixes */
			foreach ( $to_clear as $link => $dummy ) {
				unset ( $to_clear [ $link ]);
				$to_clear[ $this->options[ 'prefix_meta' ] . $link ] = true;
				$to_clear[ $this->options[ 'prefix_data' ] . $link ] = true;
			}

			/* run clear */
			$internal = $this->proxy ( 'clear' );
			$this->$internal ( $to_clear );
		}

		/**
		 * to collect all permalinks of all taxonomy terms used in invalidation & precache
		 *
		 * @param array &$links Passed by reference array that has to be filled up with the links
		 * @param mixed $site Site ID or false; used in WordPress Network
		 *
		 */
		public function taxonomy_links ( &$links, $site = false ) {

			if ( $site !== false ) {
				$current_blog = get_current_blog_id();
				switch_to_blog( $site );

				$url = get_blog_option ( $site, 'siteurl' );
				if ( substr( $url, -1) !== '/' )
					$url = $url . '/';

				$links[ $url ] = true;
			}

			/* we're only interested in public taxonomies */
			$args = array(
				'public'   => true,
			);

			/* get taxonomies as objects */
			$taxonomies = get_taxonomies( $args, 'objects' );

			if ( !empty( $taxonomies ) ) {
				foreach ( $taxonomies  as $taxonomy ) {
					/* reset array, just in case */
					$terms = array();

					/* get all the terms for this taxonomy, only if not empty */
					$sargs = array(
						'hide_empty'    => true,
						'fields'        => 'all',
						'hierarchical'  =>false,
					);
					$terms = get_terms ( $taxonomy->name , $sargs );

					if ( !empty ( $terms ) ) {
						foreach ( $terms as $term ) {
							/* get the permalink for the term */
							$link = get_term_link ( $term->slug, $taxonomy->name );
							/* add to container */
							$links[ $link ] = true;
							/* remove the taxonomy name from the link, lots of plugins remove this for SEO, it's better to include them than leave them out
							   in worst case, we cache some 404 as well
							*/
							$link = str_replace ( '/'.$taxonomy->rewrite['slug'], '', $link  );
							/* add to container */
							$links[ $link ] = true;
						}
					}
				}
			}

			/* switch back to original site if we navigated away */
			if ( $site !== false ) {
				switch_to_blog( $current_blog );
			}

		}

		/**
		 * get backend aliveness
		 *
		 * @return array Array of configured servers with aliveness value
		 *
		 */
		public function status () {

			/* look for backend aliveness, exit on inactive backend */
			if ( ! $this->is_alive() )
				return false;

			$internal = $this->proxy ( 'status' );
			$this->$internal();
			return $this->status;
		}

		/**
		 * backend proxy function name generator
		 *
		 * @return string Name of internal function based on cache_type
		 *
		 */
		private function proxy ( $method ) {
			return $this->options['cache_type'] . '_' . $method;
		}

		/**
		 * function to check backend aliveness
		 *
		 * @return boolean true if backend is alive, false if not
		 *
		 */
		private function is_alive() {
			if ( ! $this->alive ) {
				$this->log (  __translate__("backend is not active, exiting function ", $this->plugin_constant ) . __FUNCTION__, LOG_WARNING );
				return false;
			}

			return true;
		}

		/**
		 * split hosts string to backend servers
		 *
		 *
		 */
		private function set_servers () {
			/* replace servers array in config according to hosts field */
			$servers = explode( self::host_separator , $this->options['hosts']);

			$options['servers'] = array();

			foreach ( $servers as $snum => $sstring ) {

				$separator = strpos( $sstring , self::port_separator );
				$host = substr( $sstring, 0, $separator );
				$port = substr( $sstring, $separator + 1 );
				// unix socket failsafe
				if ( empty ($port) ) $port = 0;

				$this->options['servers'][$sstring] = array (
					'host' => $host,
					'port' => $port
				);
			}

		}

		/**
		 * get current array of servers
		 *
		 * @return array Server list in current config
		 *
		 */
		public function get_servers () {
			$r = isset ( $this->options['servers'] ) ? $this->options['servers'] : '';
			return $r;
		}

		/**
		 * log wrapper to include options
		 *
		 * @var mixed $message Message to log
		 * @var int $log_level Log level
		 */
		private function log ( $message, $log_level = LOG_WARNING ) {
			if ( !isset ( $this->options['log'] ) || $this->options['log'] != 1 )
				return false;
			else
				$this->utilities->log ( $this->plugin_constant , $message, $log_level );
		}

		/*********************** END PUBLIC FUNCTIONS ***********************/
		/*********************** APC FUNCTIONS ***********************/
		/**
		 * init apc backend: test APC availability and set alive status
		 */
		private function apc_init () {
			/* verify apc functions exist, apc extension is loaded */
			if ( ! function_exists( 'apc_sma_info' ) ) {
				$this->log (  __translate__('APC extension missing', $this->plugin_constant ) );
				return false;
			}

			/* verify apc is working */
			if ( apc_sma_info() ) {
				$this->log (  __translate__('backend OK', $this->plugin_constant ) );
				$this->alive = true;
			}
		}

		/**
		 * health checker for APC
		 *
		 * @return boolean Aliveness status
		 *
		 */
		private function apc_status () {
			$this->status = true;
			return $this->alive;
		}

		/**
		 * get function for APC backend
		 *
		 * @param string $key Key to get values for
		 *
		 * @return mixed Fetched data based on key
		 *
		*/
		private function apc_get ( &$key ) {
			return apc_fetch( $key );
		}

		/**
		 * Set function for APC backend
		 *
		 * @param string $key Key to set with
		 * @param mixed $data Data to set
		 *
		 * @return boolean APC store outcome
		 */
		private function apc_set (  &$key, &$data ) {
			return apc_store( $key , $data , $this->options['expire'] );
		}


		/**
		 * Flushes APC user entry storage
		 *
		 * @return boolean APC flush outcome status
		 *
		*/
		private function apc_flush ( ) {
			return apc_clear_cache('user');
		}

		/**
		 * Removes entry from APC or flushes APC user entry storage
		 *
		 * @param mixed $keys Keys to clear, string or array
		*/
		private function apc_clear ( &$keys ) {
			/* make an array if only one string is present, easier processing */
			if ( !is_array ( $keys ) )
				$keys = array ( $keys => true );

			foreach ( $keys as $key => $dummy ) {
				if ( ! apc_delete ( $key ) ) {
					$this->log (  __translate__('Failed to delete APC entry: ', $this->plugin_constant ) . $key, LOG_ERR );
					//throw new Exception ( __translate__('Deleting APC entry failed with key ', $this->plugin_constant ) . $key );
				}
				else {
					$this->log (  __translate__( 'APC entry delete: ', $this->plugin_constant ) . $key );
				}
			}
		}

		/*********************** END APC FUNCTIONS ***********************/

		/*********************** MEMCACHED FUNCTIONS ***********************/
		/**
		 * init memcached backend
		 */
		private function memcached_init () {
			/* Memcached class does not exist, Memcached extension is not available */
			if (!class_exists('Memcached')) {
				$this->log (  __translate__(' Memcached extension missing', $this->plugin_constant ), LOG_ERR );
				return false;
			}

			/* check for existing server list, otherwise we cannot add backends */
			if ( empty ( $this->options['servers'] ) && ! $this->alive ) {
				$this->log (  __translate__("Memcached servers list is empty, init failed", $this->plugin_constant ), LOG_WARNING );
				return false;
			}

			/* check is there's no backend connection yet */
			if ( $this->connection === NULL ) {
				/* persistent backend needs an identifier */
				if ( $this->options['persistent'] == '1' )
					$this->connection = new Memcached( $this->plugin_constant );
				else
					$this->connection = new Memcached();

				/* use binary and not compressed format, good for nginx and still fast */
				$this->connection->setOption( Memcached::OPT_COMPRESSION , false );
				$this->connection->setOption( Memcached::OPT_BINARY_PROTOCOL , true );
			}

			/* check if initialization was success or not */
			if ( $this->connection === NULL ) {
				$this->log (  __translate__( 'error initializing Memcached PHP extension, exiting', $this->plugin_constant ) );
				return false;
			}

			/* check if we already have list of servers, only add server(s) if it's not already connected */
			$servers_alive = array();
			if ( !empty ( $this->status ) ) {
				$servers_alive = $this->connection->getServerList();
				/* create check array if backend servers are already connected */
				if ( !empty ( $servers ) ) {
					foreach ( $servers_alive as $skey => $server ) {
						$skey =  $server['host'] . ":" . $server['port'];
						$servers_alive[ $skey ] = true;
					}
				}
			}

			/* adding servers */
			foreach ( $this->options['servers'] as $server_id => $server ) {
				/* reset server status to unknown */
				$this->status[$server_id] = -1;

				/* only add servers that does not exists already  in connection pool */
				if ( !@array_key_exists($server_id , $servers_alive ) ) {
					$this->connection->addServer( $server['host'], $server['port'] );
					$this->log (  $server_id . __translate__(" added, persistent mode: ", $this->plugin_constant ) . $this->options['persistent'] );
				}
			}

			/* backend is now alive */
			$this->alive = true;
			$this->memcached_status();
		}

		/**
		 * sets current backend alive status for Memcached servers
		 *
		 */
		private function memcached_status () {
			/* server status will be calculated by getting server stats */
			$this->log (  __translate__("checking server statuses", $this->plugin_constant ));
			/* get servers statistic from connection */
			$report =  $this->connection->getStats();

			foreach ( $report as $server_id => $details ) {
				/* reset server status to offline */
				$this->status[$server_id] = 0;
				/* if server uptime is not empty, it's most probably up & running */
				if ( !empty($details['uptime']) ) {
					$this->log (  $server_id . __translate__(" server is up & running", $this->plugin_constant ));
					$this->status[$server_id] = 1;
				}
			}

		}

		/**
		 * get function for Memcached backend
		 *
		 * @param string $key Key to get values for
		 *
		*/
		private function memcached_get ( &$key ) {
			return $this->connection->get($key);
		}

		/**
		 * Set function for Memcached backend
		 *
		 * @param string $key Key to set with
		 * @param mixed $data Data to set
		 *
		 */
		private function memcached_set ( &$key, &$data ) {
			$result = $this->connection->set ( $key, $data , $this->options['expire']  );

			/* if storing failed, log the error code */
			if ( $result === false ) {
				$code = $this->connection->getResultCode();
				$this->log (  __translate__('unable to set entry ', $this->plugin_constant ) . $key . __translate__( ', Memcached error code: ', $this->plugin_constant ) . $code );
				//throw new Exception ( __translate__('Unable to store Memcached entry ', $this->plugin_constant ) . $key . __translate__( ', error code: ', $this->plugin_constant ) . $code );
			}

			return $result;
		}

		/**
		 *
		 * Flush memcached entries
		 */
		private function memcached_flush ( ) {
			return $this->connection->flush();
		}


		/**
		 * Removes entry from Memcached or flushes Memcached storage
		 *
		 * @param mixed $keys String / array of string of keys to delete entries with
		*/
		private function memcached_clear ( &$keys ) {

			/* make an array if only one string is present, easier processing */
			if ( !is_array ( $keys ) )
				$keys = array ( $keys => true );

			foreach ( $keys as $key => $dummy ) {
				$kresult = $this->connection->delete( $key );

				if ( $kresult === false ) {
					$code = $this->connection->getResultCode();
					$this->log (  __translate__('unable to delete entry ', $this->plugin_constant ) . $key . __translate__( ', Memcached error code: ', $this->plugin_constant ) . $code );
				}
				else {
					$this->log (  __translate__( 'entry deleted: ', $this->plugin_constant ) . $key );
				}
			}
		}
		/*********************** END MEMCACHED FUNCTIONS ***********************/

		/*********************** MEMCACHE FUNCTIONS ***********************/
		/**
		 * init memcache backend
		 */
		private function memcache_init () {
			/* Memcached class does not exist, Memcache extension is not available */
			if (!class_exists('Memcache')) {
				$this->log (  __translate__('PHP Memcache extension missing', $this->plugin_constant ), LOG_ERR );
				return false;
			}

			/* check for existing server list, otherwise we cannot add backends */
			if ( empty ( $this->options['servers'] ) && ! $this->alive ) {
				$this->log (  __translate__("servers list is empty, init failed", $this->plugin_constant ), LOG_WARNING );
				return false;
			}

			/* check is there's no backend connection yet */
			if ( $this->connection === NULL )
				$this->connection = new Memcache();

			/* check if initialization was success or not */
			if ( $this->connection === NULL ) {
				$this->log (  __translate__( 'error initializing Memcache PHP extension, exiting', $this->plugin_constant ) );
				return false;
			}

			/* adding servers */
			foreach ( $this->options['servers'] as $server_id => $server ) {
				if ( $this->options['persistent'] == '1' )
					$conn = 'pconnect';
				else
					$conn = 'connect';

					/* in case of unix socket */
				if ( $server['port'] === 0 )
					$this->status[$server_id] = $this->connection->$conn ( 'unix:/' . $server['host'] );
				else
					$this->status[$server_id] = $this->connection->$conn ( $server['host'] , $server['port'] );

				$this->log ( $server_id . __translate__(" added, persistent mode: ", $this->plugin_constant ) . $this->options['persistent'] );
			}

			/* backend is now alive */
			$this->alive = true;
			$this->memcache_status();
		}

		/**
		 * check current backend alive status for Memcached
		 *
		 */
		private function memcache_status () {
			/* server status will be calculated by getting server stats */
			$this->log (  __translate__("checking server statuses", $this->plugin_constant ));
			/* get servers statistic from connection */
			foreach ( $this->options['servers'] as $server_id => $server ) {
				$this->status[$server_id] = $this->connection->getServerStatus( $server['host'], $server['port'] );
				if ( $this->status[$server_id] == 0 )
					$this->log ( $server_id . __translate__(" server is down", $this->plugin_constant ));
				else
					$this->log ( $server_id . __translate__(" server is up & running", $this->plugin_constant ));
			}
		}

		/**
		 * get function for Memcached backend
		 *
		 * @param string $key Key to get values for
		 *
		*/
		private function memcache_get ( &$key ) {
			return $this->connection->get($key);
		}

		/**
		 * Set function for Memcached backend
		 *
		 * @param string $key Key to set with
		 * @param mixed $data Data to set
		 *
		 */
		private function memcache_set ( &$key, &$data ) {
			$result = $this->connection->set ( $key, $data , 0 , $this->options['expire'] );
			return $result;
		}

		/**
		 *
		 * Flush memcached entries
		 */
		private function memcache_flush ( ) {
			return $this->connection->flush();
		}


		/**
		 * Removes entry from Memcached or flushes Memcached storage
		 *
		 * @param mixed $keys String / array of string of keys to delete entries with
		*/
		private function memcache_clear ( &$keys ) {
			/* make an array if only one string is present, easier processing */
			if ( !is_array ( $keys ) )
				$keys = array ( $keys => true );

			foreach ( $keys as $key => $dummy ) {
				$kresult = $this->connection->delete( $key );

				if ( $kresult === false ) {
					$this->log (  __translate__('unable to delete entry ', $this->plugin_constant ) . $key );
				}
				else {
					$this->log (  __translate__( 'entry deleted: ', $this->plugin_constant ) . $key );
				}
			}
		}

		/*********************** END MEMCACHE FUNCTIONS ***********************/

	}

}

?>
