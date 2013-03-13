<?php

if ( ! class_exists( 'WP_FFPC' ) ) {

	/* get the plugin abstract class*/
	include_once ( 'wp-ffpc-abstract.php');
	/* get the common functions class*/
	include_once ( 'wp-ffpc-backend.php');

	/**
	 * main wp-ffpc class
	 *
	 * @var string $acache_config Configuration storage file location
	 * @var string $acache_worker The advanced cache worker file location
	 * @var string $acache The WordPress standard advanced cache location
	 * @var array $select_cache_type Possible cache types array
	 * @var array $select_invalidation_method Possible invalidation methods array
	 * @var string $nginx_sample Nginx example config file location
	 *
	 */
	class WP_FFPC extends WP_Plugins_Abstract {
		private $global_config_var = '$wp_ffpc_config';
		private $global_config_key = '';
		private $global_config = array();
		private $acache_config = '';
		private $acache_worker = '';
		private $acache = '';
		private $nginx_sample = '';
		private $acache_common = '';
		const host_separator  = ',';
		const port_separator  = ':';



		protected $select_cache_type = array ();
		protected $select_invalidation_method = array ();

		/**
		 * init hook function runs before admin panel hook & themeing activated
		 */
		public function plugin_init() {
			$this->acache_config = $this->plugin_dir . $this->plugin_constant . '-config.php';
			$this->acache_worker = $this->plugin_dir . $this->plugin_constant . '-acache.php';
			$this->acache = ABSPATH . 'wp-content/advanced-cache.php';
			$this->nginx_sample = $this->plugin_dir . $this->plugin_constant . '-nginx-sample.conf';
			$this->acache_common = $this->plugin_dir . $this->plugin_constant . '-common.php';

			if ( $this->network )
				$this->global_config_key = 'network';
			else
				$this->global_config_key = $_SERVER['HTTP_HOST'];

			$this->select_cache_type = array (
				'apc' => __( 'APC' , $this->plugin_constant ),
				'memcache' => __( 'PHP Memcache' , $this->plugin_constant ),
				'memcached' => __( 'PHP Memcached' , $this->plugin_constant ),
			);

			$this->select_invalidation_method = array (
				0 => __( 'flush cache' , $this->plugin_constant ),
				1 => __( 'only modified post' , $this->plugin_constant ),
			);
		}

		/**
		 * activation hook function, to be extended
		 */
		public function plugin_activate() {
			$this->plugin_options_save();
		}

		/**
		 * deactivation hook function, to be extended
		 */
		public function plugin_deactivate () {
			$this->update_acache( true );
		}

		/**
		 * uninstall hook function, to be extended
		 */
		public function plugin_uninstall() {
			/* delete plugin config array file */
			unlink ( $this->acache_config );
			/* delete advanced-cache.php file */
			unlink ( $this->acache );
			/* delete site settings */
			$this->plugin_options_delete ();
		}

		/**
		 * admin panel, the HTML usually
		 */
		public function plugin_admin_panel() {
			/**
			 * security
			 */
			if( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ){
				die( );
			}

			/**
			 * if options were saved
			 */
			if ($_GET['saved']=='true' || $this->status == 1) : ?>
				<div class='updated settings-error'><p><strong>Settings saved.</strong></p></div>
			<?php endif;

			/**
			 * if options were saved
			 */
			if ($_GET['deleted']=='true' || $this->status == 2) : ?>
				<div class='error'><p><strong>Plugin options deleted.</strong></p></div>
			<?php endif;

			/**
			 * the admin panel itself
			 */
			?>

			<script>
				jQuery(document).ready(function($) {
					jQuery( "#<?php echo $this->plugin_constant ?>-settings" ).tabs();
				});
			</script>

			<div class="wrap">

			<h4>This plugin helped your business? <a href="<?php echo WP_FFPC_DONATION_LINK; ?>">Buy me a coffee for having it, please :)</a></h4>

			<?php if ( ! WP_CACHE ) : ?>
				<div class="error"><p><strong><?php _e("WP_CACHE is disabled, plugin will not work that way. Please add define `( 'WP_CACHE', true );` in wp-config.php", $this->plugin_constant ); ?></strong></p></div>
			<?php endif; ?>

			<?php if ( ! file_exists ( $this->acache ) || ! file_exists ( $this->acache_config ) ) : ?>
				<div class="error"><p><strong><?php _e("WARNING: advanced cache file is yet to be generated, please save settings!", $this->plugin_constant); ?></strong></p></div>
			<?php endif; ?>

			<?php if ( $this->options['cache_type'] == 'memcached' && !class_exists('Memcached') ) : ?>
				<div class="error"><p><strong><?php _e('ERROR: Memcached cache backend activated but no PHP memcached extension was found.', $this->plugin_constant); ?></strong></p></div>
			<?php endif; ?>

			<?php if ( $this->options['cache_type'] == 'memcache' && !class_exists('Memcache') ) : ?>
				<div class="error"><p><strong><?php _e('ERROR: Memcache cache backend activated but no PHP memcache extension was found.', $this->plugin_constant); ?></strong></p></div>
			<?php endif; ?>

			<?php
				/* get the current runtime configuration for memcache in PHP */
				$memcache_settings = ini_get_all( 'memcache' );
				if ( !empty ( $memcache_settings ) && $this->options['cache_type'] == 'memcache' )
				{
					$memcache_protocol = strtolower($memcache_settings['memcache.protocol']['local_value']);
					if ( $memcached_protocol == 'binary' ) :
					?>
					<div class="error"><p><strong><?php _e('WARNING: Memcache extension is configured to use binary mode. This is very buggy and the plugin will most probably not work correctly. <br />Please consider to change either to ASCII mode or to Mecached extension.', $this->plugin_constant ); ?></strong></p></div>
					<?php
					endif;
				}
			?>
			<div class="updated">
				<?php $this->backend = new WP_FFPC_Backend ( $this->options ); ?>
				<p><strong><?php _e ( 'Driver: ' , $this->plugin_constant); echo $this->options['cache_type']; ?></strong></p>
				<?php
					/* only display backend status if memcache-like extension is running */
					if ( strstr ( $this->options['cache_type'], 'memcache') ) :
						?><p><?php
						_e( '<strong>Backend status:</strong><br />', $this->plugin_constant );

						/* we need to go through all servers */
						$servers = $this->backend->status();
						foreach ( $servers as $server_string => $status ) {
							echo $server_string ." => ";

							if ( $status == 0 )
								_e ( '<span class="error-msg">down</span><br />', $this->plugin_constant );
							elseif ( $status == 1 )
								_e ( '<span class="ok-msg">up & running</span><br />', $this->plugin_constant );
							else
								_e ( '<span class="error-msg">unknown, please try re-saving settings!</span><br />', $this->plugin_constant );
						}

						?></p><?php
					endif;
				?>
			</div>
			<h2><?php echo $plugin_name ; _e( ' settings', $this->plugin_constant ) ; ?></h2>
			<form method="post" action="#" id="<?php echo $this->plugin_constant ?>-settings" class="plugin-admin">

				<ul class="tabs">
					<li><a href="#<?php echo $this->plugin_constant ?>-type" class="wp-switch-editor"><?php _e( 'Cache type', $this->plugin_constant ); ?></a></li>
					<li><a href="#<?php echo $this->plugin_constant ?>-debug" class="wp-switch-editor"><?php _e( 'Debug & in-depth', $this->plugin_constant ); ?></a></li>
					<li><a href="#<?php echo $this->plugin_constant ?>-exceptions" class="wp-switch-editor"><?php _e( 'Cache exceptions', $this->plugin_constant ); ?></a></li>
					<li><a href="#<?php echo $this->plugin_constant ?>-memcached" class="wp-switch-editor"><?php _e( 'Memcache(d)', $this->plugin_constant ); ?></a></li>
					<li><a href="#<?php echo $this->plugin_constant ?>-nginx" class="wp-switch-editor"><?php _e( 'nginx', $this->plugin_constant ); ?></a></li>
				</ul>

				<fieldset id="<?php echo $this->plugin_constant ?>-type">
				<legend><?php _e( 'Set cache type', $this->plugin_constant ); ?></legend>
				<dl>
					<dt>
						<label for="cache_type"><?php _e('Select backend', $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<select name="cache_type" id="cache_type">
							<?php $this->print_select_options ( $this->select_cache_type , $this->options['cache_type'] ) ?>
						</select>
						<span class="description"><?php _e('Select backend storage driver', $this->plugin_constant); ?></span>
						<span class="default"><?php $this->print_default ( 'cache_type' ); ?></span>
					</dd>

					<dt>
						<label for="expire"><?php _e('Expiration time (ms)', $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="number" name="expire" id="expire" value="<?php echo $this->options['expire']; ?>" />
						<span class="description"><?php _e('Sets validity time of entry in milliseconds', $this->plugin_constant); ?></span>
						<span class="default"><?php $this->print_default ( 'expire' ); ?></span>
					</dd>

					<dt>
						<label for="charset"><?php _e('Charset', $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="text" name="charset" id="charset" value="<?php echo $this->options['charset']; ?>" />
						<span class="description"><?php _e('Charset of HTML and XML (pages and feeds) data.', $this->plugin_constant); ?></span>
						<span class="default"><?php $this->print_default ( 'charset' ); ?></span>
					</dd>

					<dt>
						<label for="invalidation_method"><?php _e('Cache invalidation method', $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<select name="invalidation_method" id="invalidation_method">
							<?php $this->print_select_options ( $this->select_invalidation_method , $this->options['invalidation_method'] ) ?>
						</select>
						<span class="description"><?php _e('Select cache invalidation method. <p><strong>WARNING! When selection "all", the cache will be fully flushed, including elements that were set by other applications.</strong></p>', $this->plugin_constant); ?></span>
						<span class="default"><?php $this->print_default ( 'invalidation_method' ); ?></span>
					</dd>

					<dt>
						<label for="prefix_data"><?php _e('Data prefix', $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="text" name="prefix_data" id="prefix_data" value="<?php echo $this->options['prefix_data']; ?>" />
						<span class="description"><?php _e('Prefix for HTML content keys, can be used in nginx.', $this->plugin_constant); ?></span>
						<span class="default"><?php $this->print_default ( 'prefix_data' ); ?></span>
					</dd>

					<dt>
						<label for="prefix_meta"><?php _e('Meta prefix', $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="text" name="prefix_meta" id="prefix_meta" value="<?php echo $this->options['prefix_meta']; ?>" />
						<span class="description"><?php _e('Prefix for meta content keys, used only with PHP processing.', $this->plugin_constant); ?></span>
						<span class="default"><?php $this->print_default ( 'prefix_meta' ); ?></span>
					</dd>
				</dl>
				</fieldset>

				<fieldset id="<?php echo $this->plugin_constant ?>-debug">
				<legend><?php _e( 'Debug & in-depth settings', $this->plugin_constant ); ?></legend>
				<dl>
					<dt>
						<label for="log"><?php _e("Enable logging", $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="log" id="log" value="1" <?php checked($this->options['log'],true); ?> />
						<span class="description"><?php _e('Enables ERROR and WARNING level syslog messages. Requires PHP syslog function.', $this->plugin_constant); ?></span>
						<span class="default"><?php $this->print_default ( 'log' ); ?></span>
					</dd>

					<dt>
						<label for="log_info"><?php _e("Enable information log", $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="log_info" id="log_info" value="1" <?php checked($this->options['log_info'],true); ?> />
						<span class="description"><?php _e('Enables INFO level messages; carefull, plugin is really talkative. Requires PHP syslog function.', $this->plugin_constant); ?></span>
						<span class="default"><?php $this->print_default ( 'log_info' ); ?></span>
					</dd>

					<dt>
						<label for="response_header"><?php _e("Add X-Cache-Engine header", $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="response_header" id="response_header" value="1" <?php checked($this->options['response_header'],true); ?> />
						<span class="description"><?php _e('Add X-Cache-Engine HTTP header to HTTP responses.', $this->plugin_constant); ?></span>
						<span class="default"><?php $this->print_default ( 'response_header' ); ?></span>
					</dd>

					<dt>
						<label for="sync_protocols"><?php _e("Enable sync protocolls", $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="sync_protocols" id="sync_protocols" value="1" <?php checked($this->options['sync_protocols'],true); ?> />
						<span class="description"><?php _e('Enable to replace every protocol to the same as in the request for site\'s domain', $this->plugin_constant); ?></span>
						<span class="default"><?php $this->print_default ( 'sync_protocols' ); ?></span>
					</dd>
				</dl>
				</fieldset>

				<fieldset id="<?php echo $this->plugin_constant ?>-exceptions">
				<legend><?php _e( 'Set cache additions/excepions', $this->plugin_constant ); ?></legend>
				<dl>
					<dt>
						<label for="cache_loggedin"><?php _e('Enable cache for logged in users', $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="cache_loggedin" id="cache_loggedin" value="1" <?php checked($this->options['cache_loggedin'],true); ?> />
						<span class="description"><?php _e('Cache pages even if user is logged in.', $this->plugin_constant); ?></span>
						<span class="default"><?php $this->print_default ( 'cache_loggedin' ); ?></span>
					</dd>

					<dt>
						<label for="nocache_home"><?php _e("Don't cache home", $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="nocache_home" id="nocache_home" value="1" <?php checked($this->options['nocache_home'],true); ?> />
						<span class="description"><?php _e('Exclude home page from caching', $this->plugin_constant); ?></span>
						<span class="default"><?php $this->print_default ( 'nocache_home' ); ?></span>
					</dd>

					<dt>
						<label for="nocache_feed"><?php _e("Don't cache feeds", $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="nocache_feed" id="nocache_feed" value="1" <?php checked($this->options['nocache_feed'],true); ?> />
						<span class="description"><?php _e('Exclude feeds from caching.', $this->plugin_constant); ?></span>
						<span class="default"><?php $this->print_default ( 'nocache_feed' ); ?></span>
					</dd>

					<dt>
						<label for="nocache_archive"><?php _e("Don't cache archives", $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="nocache_archive" id="nocache_archive" value="1" <?php checked($this->options['nocache_archive'],true); ?> />
						<span class="description"><?php _e('Exclude archives from caching.', $this->plugin_constant); ?></span>
						<span class="default"><?php $this->print_default ( 'nocache_archive' ); ?></span>
					</dd>

					<dt>
						<label for="nocache_single"><?php _e("Don't cache posts (and single-type entries)", $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="nocache_single" id="nocache_single" value="1" <?php checked($this->options['nocache_single'],true); ?> />
						<span class="description"><?php _e('Exclude singles from caching.', $this->plugin_constant); ?></span>
						<span class="default"><?php $this->print_default ( 'nocache_single' ); ?></span>
					</dd>

					<dt>
						<label for="nocache_page"><?php _e("Don't cache pages", $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="nocache_page" id="nocache_page" value="1" <?php checked($this->options['nocache_page'],true); ?> />
						<span class="description"><?php _e('Exclude pages from caching.', $this->plugin_constant); ?></span>
						<span class="default"><?php $this->print_default ( 'nocache_page' ); ?></span>
					</dd>
				</dl>
				</fieldset>

				<fieldset id="<?php echo $this->plugin_constant ?>-memcached">
				<legend><?php _e('Settings for memcached backend', $this->plugin_constant); ?></legend>
				<dl>
					<dt>
						<label for="hosts"><?php _e('Hosts', $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="text" name="hosts" id="hosts" value="<?php echo $this->options['hosts']; ?>" />
						<span class="description"><?php _e('List all valid like host:port,host:port,... <br />No spaces are allowed, please stick to use ":" for separating host and port and "," for separating entries. Do not add trailing ",".', $this->plugin_constant); ?></span>
						<span class="default"><?php $this->print_default ( 'hosts' ); ?></span>
					</dd>
					<dt>
						<label for="persistent"><?php _e('Persistent memcache connections', $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="persistent" id="persistent" value="1" <?php checked($this->options['persistent'],true); ?> />
						<span class="description"><?php _e('Make all memcache(d) connections persistent. Be carefull with this setting, always test the outcome.', $this->plugin_constant); ?></span>
						<span class="default"><?php $this->print_default ( 'persistent' ); ?></span>
					</dd>
				</dl>
				</fieldset>

				<fieldset id="<?php echo $this->plugin_constant ?>-nginx">
				<legend><?php _e('Sample config for nginx to utilize the data entries', $this->plugin_constant); ?></legend>
				<pre><?php echo $this->nginx_example(); ?></pre>
				</fieldset>

				<p class="clear">
					<input class="button-primary" type="submit" name="<?php echo $this->button_save ?>" id="<?php echo $this->button_save ?>" value="<?php _e('Save Changes', $this->plugin_constant ) ?>" />
					<input class="button-secondary" style="float: right" type="submit" name="<?php echo $this->button_delete ?>" id="<?php echo $this->button_delete ?>" value="<?php _e('Delete options from DB', $this->plugin_constant ) ?>" />
				</p>

			</form>
			</div>
			<?php
		}

		private function nginx_example () {
			$nginx = file_get_contents ( $this->nginx_sample );

			$loggedin = '# avoid cache for logged in users
				if ($http_cookie ~* "comment_author_|wordpressuser_|wp-postpass_" ) {
					set $memcached_request 0;
				}';

			$search = array( 'DATAPREFIX', 'MEMCACHEDHOST', 'MEMCACHEDPORT' );
			$replace = array ( $this->options['prefix_data'], $this->options['host'], $this->options['port'] );

			$nginx = str_replace ( $search , $replace , $nginx );

			/* set upstream servers */
			$servers = $this->backend->get_servers();
			foreach ( array_keys( $servers ) as $server ) {
				$nginx_servers .= "		server ". $server .";\n";
			}
			$nginx = str_replace ( 'MEMCACHED_SERVERS' , $nginx_servers , $nginx );

			/* logged in cache */
			if ( ! $this->options['cache_loggedin'])
				$nginx = str_replace ( 'LOGGEDIN_EXCEPTION' , $loggedin , $nginx );
			else
				$nginx = str_replace ( 'LOGGEDIN_EXCEPTION' , '' , $nginx );


			return $nginx;
		}

		public function plugin_hook_options_save( $activating ) {
			if ( !file_exists ( $this->acache ) || $activating )
				$this->deploy_acache();

			$this->update_acache_config();
		}

		public function plugin_hook_options_read( &$options ) {

		}

		public function plugin_hook_options_migrate( &$options ) {

		}

		public function plugin_hook_options_delete(  ) {
		}

		private function deploy_acache( ) {
			/* in case advanced-cache.php was already there, remove it */
			if ( @file_exists( $this->acache ))
				unlink ($this->acache);

			/* is deletion was unsuccessful, die, we have no rights to do that */
			if ( @file_exists( $this->acache ))
				return false;

			$string[] = "<?php";
			$string[] = "include_once ('" . $this->acache_common . "');";
			$string[] = "eval ( '". $this->global_config_var ." = ' . file_get_contents ( '" . $this->acache_config . "' ) . ';' );";
			//$string[] = 'global '. $this->global_config_var . ';';
			$string[] = "include_once ('" . $this->acache_worker . "');";

			$string[] = "?>";

			return file_put_contents( $this->acache, join( "\n" , $string ) );
		}

		private function update_acache_config ( $remove_site = false ) {

			eval ( '$this->global_config = ' . file_get_contents ( $this->acache_config ) . ';' );
			$this->global_config[ $this->global_config_key ] = $this->options;
			return file_put_contents( $this->acache_config , var_export( $this->global_config , true ) );
		}

	}
}

?>
