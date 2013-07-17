<?php
/**
 * utilities singleton class of WP-* plugins from hello@petermolnar.eu
 * version 1
 */

if (!class_exists('WP_Plugins_Utilities_v1')) {

	class WP_Plugins_Utilities_v1 {

		/**
		 * Private ctor so nobody else can instance it
		 *
		 * fun lines from lnwdr.de
		 */
		protected function __construct() {
			//Thou shalt not construct that which is unconstructable!
		}

		protected function __clone() {
			//Me not like clones! Me smash clones!
		}

		/**
		 * Init singleton
		 */
		public static function Utility() {
			static $inst = null;
			if ($inst === null) {
				$inst = new WP_Plugins_Utilities_v1();
			}
			return $inst;
		}

		/**
		 * standard log message
		 *
		 * @param string $identifier process identifier
		 * @param string $message message to add besides basic info
		 * @param int $log_level [optional] Level of log, warning by default
		 *
		 */
		public function log ( $identifier, $message, $log_level = LOG_WARNING ) {

			if ( function_exists( 'trigger_error' ) ) {
				if ( @is_array( $message ) || @is_object ( $message ) )
					$message = serialize($message);

				switch ( $log_level ) {
					case LOG_ERR:
						/* instead of E_USER_ERROR, we use warning, because ERROR would stop the script */
						trigger_error ( $identifier . " " . $message, E_USER_WARNING );
						break;
					//case LOG_WARNING:
					//	trigger_error ( $identifier . " " . $message, E_USER_WARNING );
					//	break;
					default:
						/* info level will only be fired if WP_DEBUG is active */
						if ( WP_DEBUG == true ) trigger_error ( $identifier . " " . $message, E_USER_NOTICE );
						break;
				}
			}

		}

		/**
		 * replaces http:// with https:// in an url if server is currently running on https
		 *
		 * @param string $url URL to check
		 *
		 * @return string URL with correct protocol
		 *
		 */
		public function replace_if_ssl ( $url ) {
			if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' )
				$_SERVER['HTTPS'] = 'on';

			if ( isset($_SERVER['HTTPS']) && (( strtolower($_SERVER['HTTPS']) == 'on' )  || ( $_SERVER['HTTPS'] == '1' ) ))
				$url = str_replace ( 'http://' , 'https://' , $url );

			return $url;
		}

		/**
		 * syslog log message
		 *
		 * @param string $identifier process identifier
		 * @param string $message message to add besides basic info
		 * @param int $log_level [optional] Level of log, info by default
		 *
		 */
		public function syslog ( $identifier, $message, $log_level = LOG_INFO ) {

			if ( function_exists( 'syslog' ) && function_exists ( 'openlog' ) ) {
				if ( @is_array( $message ) || @is_object ( $message ) )
					$message = serialize($message);

				switch ( $log_level ) {
					case LOG_ERR :
						openlog('wordpress('.$_SERVER['HTTP_HOST'].')',LOG_NDELAY|LOG_PERROR,LOG_SYSLOG);
						break;
					default:
						openlog('wordpress(' .$_SERVER['HTTP_HOST']. ')', LOG_NDELAY,LOG_SYSLOG);
						break;
				}

				syslog( $log_level , $identifier . $message );
			}
		}
	}
}
