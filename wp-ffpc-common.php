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
	/**
	 *
	 * @var mixed	$connection	Backend object storage variable
	 * @var array	$config		Configuration settings array
	 * @var boolean	$alive		Backend aliveness indicator
	 * @var mixed	$status		Backend server status storage
	 *
	 */
	class WP_FFPC_Backend {

		const plugin_constant = 'wp-ffpc';
		const network_key = 'network';
		const id_prefix = 'wp-ffpc-id-';
		const prefix = 'prefix-';
		const loglevel = LOG_INFO;
		private $key_prefixes = array ( 'meta', 'data' );

		private $connection;
		private $config;
		private $alive = false;
		public $status;

		/**
		* constructor
		*
		* @param mixed $config Configuration options
		*
		*/
		protected function __construct( $config = array() , $key = '' ) {

			if ( !empty ( $config ) )
			{
				$this->config = empty( $key ) ? $config : $config[ $key ];
			}
			/* 	in case of missing passed config array, use global */
			else
			{
				global $wp_ffpc_config;

				$key = empty( $key ) ? $_SERVER['HTTP_HOST'] : $key;

				/* we have network array, that means plugin is active network wide */
				if ( !empty (  $wp_ffpc_config[ self::network_key ] ) )
					$this->config = $wp_ffpc_config[ self::network_key ];
				/* if no network array, try to use host config */
				elseif ( !empty ( $wp_ffpc_config[ $key]  ) )
					$this->config = $wp_ffpc_config[ $key ];
				/* no config was found for key */
			}

			if ( empty ( $this->config ) )
				throw new Exception( __( 'Configuration is empty, exiting constructor') );

			/* call backend initiator based on cache type */
			$init = $this->config['cache_type'] . '_init';
			$this->_syslog ( __(' init starting ', self::plugin_constant ));
			$this->$init();
		}

		/*********************** PUBLIC FUNCTIONS ***********************/
		/**
		 * public get function, transparent proxy to internal function based on backend
		 *
		 * @param string $key Cache key to get value for
		 *
		 * @return mixed False when entry not found or entry value on success
		 */
		public function get ( &$key ) {
			if ( $this->alive )
			{
				$this->_syslog ( __(' get ', self::plugin_constant ). $key );
				$internal = $this->config['cache_type'] . '_get';
				return $this->$internal( $key );
			}

			return false;
		}

		/**
		 * public set function, transparent proxy to internal function based on backend
		 *
		 * @param string $key Cache key to set with ( reference only, for speed )
		 * @param mixed $data Data to set ( reference only, for speed )
		 */
		public function set ( &$key, &$data ) {
			if ( $this->alive )
			{
				$this->_syslog( __(' set ', self::plugin_constant ) . $key . __(' expire: ', self::plugin_constant ) . $this->config['expire']);
				$internal = $this->config['cache_type'] . '_set';
				return $this->$internal( $key, $data );
			}

			return false;
		}

		/**
		 * public get function, transparent proxy to internal function based on backend
		 *
		 * @param string $key Cache key to invalidate, false mean full flush
		 */
		public function clear ( $post_id = false ) {
			if ( $this->alive )
			{
				/* system is configured for full flush, ignore post_id */
				if ( $this->config['invalidation_method'] === 0 || empty ( $post_id ) )
				{
					$internal = $this->config['cache_type'] . '_flush';
					return $this->$internal( );
				}
			/* delete only by key */
			else
			{
				/* there's an additional entry for storing the "normal" keys with a post ID based key */
				$key = $this->apc_get ( self::id_prefix . $post_id );
				if ( !empty ( $key ) )
				{
					$todo = array ( 'meta', 'data' );
					foreach ( $todo as $prefix ) {
						$e = $this->config['prefix-' . $prefix ] . $key;
						if ( ! apc_delete ( $meta ) )
						{
							$this->_syslog ( __('Deleting APC entry failed with key ', self::plugin_constant ) . $key );
							//throw new Exception ( __('Deleting APC entry failed with key ', self::plugin_constant ) . $key );
							return false;
						}
						else
						{
							$this->_syslog ( __( 'delete APC entry with key: ', self::plugin_constant ) . $key );
						}
					}
				}
				else
				{
					/* this is not a critical error, although could mean problems */
					$this->_syslog ( __( 'Requested key not found, nothing was invalidated!' , self::plugin_constant ) );
				}
			}




				$internal = $this->config['cache_type'] . '_clear';
				return $this->$internal( $post_id );
			}

			return false;
		}

		/**
		 * get backend aliveness
		 */
		public function status () {
			if ( !$this->alive )
				return false;

			$internal = $this->config['cache_type'] . '_status';
				return $this->status;
		}

		/*********************** END PUBLIC FUNCTIONS ***********************/
		/*********************** APC FUNCTIONS ***********************/
		/**
		 * init apc backend: test APC availability and set alive status
		 */
		private function apc_init () {
			/* verify apc functions exist, apc extension is loaded */
			if ( ! function_exists( 'apc_sma_info' ) )
			{
				$this->_syslog ( __(' APC extension missing') );
				//throw new Exception( __( 'APC functions not found') );
			}

			/* verify apc is working */
			if ( apc_sma_info() )
			{
				$this->_syslog ( __(' backend OK') );
				$this->alive = true;
			}
		}

		/**
		 * get function for APC backend
		 *
		 * @param string $key Key to get values for
		 *
		*/
		private function apc_get ( &$key ) {
			/* log this query */
			return apc_fetch( $key );
		}

		/**
		 * Set function for APC backend
		 *
		 * @param string $key Key to set with
		 * @param mixed $data Data to set
		 *
		 */
		private function apc_set (  &$key, &$data ) {

			if ( ! apc_store( $key , $data , $this->config['expire'] ) )
			{
				$this->_syslog ( __('Unable to store APC cache entry ', self::plugin_constant ) . $key );
				//throw new Exception ( __('Unable to store APC cache entry ', self::plugin_constant ) . $key );
				return false;
			}

			return true;
		}

		/**
		 * Removes entry from APC or flushes APC user entry storage
		 *
		 * @param mixed $key If no key is provided, flush entire storage otherwise only delete entry with key
		*/
		private function apc_clear ( $post_id = false ) {
			/* system is configured for full flush, that's stronger */
			if ( $this->config['invalidation_method'] === 0 )
			{
				apc_clear_cache('user');
				$this->_syslog ( __(' user cache flushed', self::plugin_constant ) );
			}
			/* delete only by key */
			else
			{
				/* there's an additional entry for storing the "normal" keys with a post ID based key */
				$key = $this->apc_get ( self::id_prefix . $post_id );
				if ( !empty ( $key ) )
				{
					$todo = array ( 'meta', 'data' );
					foreach ( $todo as $prefix ) {
						$e = $this->config['prefix-' . $prefix ] . $key;
						if ( ! apc_delete ( $meta ) )
						{
							$this->_syslog ( __('Deleting APC entry failed with key ', self::plugin_constant ) . $key );
							//throw new Exception ( __('Deleting APC entry failed with key ', self::plugin_constant ) . $key );
							return false;
						}
						else
						{
							$this->_syslog ( __( 'delete APC entry with key: ', self::plugin_constant ) . $key );
						}
					}
				}
				else
				{
					/* this is not a critical error, although could mean problems */
					$this->_syslog ( __( 'Requested key not found, nothing was invalidated!' , self::plugin_constant ) );
				}
			}

			return true;
		}

		/**
		 * Flushes APC user entry storage
		 *
		*/
		private function apc_flush ( ) {

			if ( apc_clear_cache('user') )
			{
				$this->_syslog ( __(' APC user cache flushed', self::plugin_constant ) );
				return true;
			}
			else
			{
				$this->_syslog_error ( __(' failed to clean APC user cache', self::plugin_constant ) );
				return false;
			}

		}

		/*********************** END APC FUNCTIONS ***********************/
		/*********************** MEMCACHED FUNCTIONS ***********************/
		/**
		 * init memcached backend
		 */
		private function memcached_init () {
			/* Memcached class does not exist, Memcached extension is not available */
			if (!class_exists('Memcached'))
			{
				$this->_syslog ( __(' Memcached extension missing', self::plugin_constant ) );
				//throw new Exception( __( 'Memcached class not found, init failed', self::plugin_constant ) );
				return false;
			}

			/* check for existing server list, otherwise we cannot add backends */
			if ( empty ( $this->config['servers'] ) && !$this->alive )
			{
				throw new Exception( __( 'Memcached servers list is empty', self::plugin_constant ) );
				//wp_ffpc_log ( __("Memcached servers list is empty, init failed", self::plugin_constant ) );
				return false;
			}

			/* check is there's no backend connection yet */
			if ( !$this->alive )
			{
				/* persistent backend needs an identifier */
				if ( $this->config['persistent'] == '1' )
					$this->connection = new Memcached( self::plugin_constant );
				else
					$this->connection = new Memcached();

				/* use binary and not compressed format, good for nginx and still fast */
				$this->connection->setOption( Memcached::OPT_COMPRESSION , false );
				$this->connection->setOption( Memcached::OPT_BINARY_PROTOCOL , true );
				$this->alive = true;
			}

			/* check if we already have list of servers, only add server(s) if it's not already connected */
			$servers_alive = array();
			if ( !empty ( $this->status ) )
			{
				$servers_alive = $this->connection->getServerList();
				/* create check array if backend servers are already connected */
				if ( !empty ( $servers ) )
				{
					foreach ( $servers_alive as $skey => $server ) {
						$skey =  $server['host'] . ":" . $server['port'];
						$servers_alive[ $skey ] = true;
					}
				}
			}

			/* adding servers */
			foreach ( $this->config['servers'] as $server_id => $server ) {
				/* reset server status to unknown */
				$this->status[$server_id] = -1;

				/* only add servers that does not exists already  in connection pool */
				if ( !@array_key_exists($server_id , $servers_alive ) )
				{
					$this->connection->addServer( $server['host'], $server['port'] );
					wp_ffpc_log ( $server_id . __(" added, persistent mode: ", self::plugin_constant ) . $wp_ffpc_config['persistent'] );
				}
			}

			$this->memcached_status();
		}

		/**
		 * check current backend alive status for Memcached
		 *
		 */
		private function memcached_status () {
			if ( !$this->alive )
				$this->status = false;

			/* server status will be calculated by getting server stats */
			$this->_syslog ( __("checking Memcached server statuses", self::plugin_constant ));
			/* get servers statistic from connection */
			$report =  $this->connection->getStats();

			foreach ( $report as $server_id => $details ) {
				/* reset server status to offline */
				$this->status[$server_id] = 0;
				/* if server uptime is not empty, it's most probably up & running */
				if ( !empty($details['uptime']) )
				{
					$this->_syslog ( $server_id . __(" Memcached server is up & running", self::plugin_constant ));
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
		public function memcached_set ( &$key, &$data ) {
			/* if storing failed, return the error code */
			if ( !$this->connection->set ( $key, $data , $this->config['expire']  ) ) {
				$code = $this->connection->getResultCode();
				$this->_syslog ( __('Unable to store Memcached entry ', self::plugin_constant ) . $key . __( ', error code: ', self::plugin_constant ) . $code );
				//throw new Exception ( __('Unable to store Memcached entry ', self::plugin_constant ) . $key . __( ', error code: ', self::plugin_constant ) . $code );
				return $code;
			}
			else
			{
				return true;
			}
		}


		/**
		 * Removes entry from Memcached or flushes Memcached storage
		 *
		 * @param mixed $key If no key is provided, flush entire storage otherwise only delete entry with key
		*/
		public function memcached_clear ( $post_id = false ) {
			/* system is configured for full flush, that's stronger */
			if ( $wp_ffpc_config['invalidation_method'] === 0 )
			{
				apc_clear_cache('user');
				$this->_syslog ( __(' user cache flushed', self::plugin_constant ) );
			}
			/* delete only by key */
			else
			{
				/* there's an additional entry for storing the "normal" keys with a post ID based key */
				$key = $this->apc_get ( self::id_prefix . $post_id );
				if ( !empty ( $key ) )
				{
					$todo = array ( 'meta', 'data' );
					foreach ( $todo as $prefix ) {
						$e = $this->config['prefix-' . $prefix ] . $key;
						if ( ! apc_delete ( $meta ) )
							throw new Exception ( __('Deleting APC entry failed with key ', self::plugin_constant ) . $key );
						else
							$this->_syslog ( __( 'delete APC entry with key: ', self::plugin_constant ) . $key );
					}
				}
				else
				{
					/* this is not a critical error, although could mean problems */
					$this->_syslog ( __( 'Requested key not found, nothing was invalidated!' , self::plugin_constant ) );
				}
			}
		}

		/*********************** END MEMCACHED FUNCTIONS ***********************/
		/*********************** MEMCACHE FUNCTIONS ***********************/
		/*********************** END MEMCACHE FUNCTIONS ***********************/

		/**
		 * sends message to sysog
		 *
		 * @param mixed $message message to add besides basic info
		 *
		 */
		private function _syslog ( $message, $log_level = LOG_INFO ) {

			/* logging disabled */
			if ( ! $this->config['syslog'] )
				return false;

			if ( @is_array( $message ) || @is_object ( $message ) )
				$message = serialize($message);

			/* debugging enabled  and syslog function is available */
			if ( $this->log == true && function_exists('syslog') )
				syslog( $log_level , self::plugin_constant . ": " . $this->config['cache_type'] . ' ' . $message );
		}

	}

}

?>
