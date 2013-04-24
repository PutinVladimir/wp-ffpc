<?php
/**
 * main class for WordPress plugin WP-FFPC
 *
 * supported storages:
 *  - APC
 *  - Memcached
 *  - Memcache
 *
 */

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
	 * @var array $select_cache_type Cache types string array
	 * @var array $select_invalidation_method Invalidation methods string array
	 *
	 */
	class WP_FFPC extends WP_Plugins_Abstract {
		const host_separator  = ',';
		const port_separator  = ':';
		const donation_id_key = 'hosted_button_id=';
		const global_config_var = '$wp_ffpc_config';
		const key_save = 'saved';
		const key_delete = 'deleted';
		const key_flush = 'flushed';
		const slug_flush = '&flushed=true';
		const key_precache = 'precached';
		const slug_precache = '&precached=true';
		const key_precache_disabled = 'precache_disabled';
		const slug_precache_disabled = '&precache_disabled=true';
		const precache_log = 'wp-ffpc-precache.log';
		private $precache_message = '';
		private $global_option = '';
		private $global_config_key = '';
		private $global_config = array();
		private $global_saved = false;
		private $acache_worker = '';
		private $acache = '';
		private $nginx_sample = '';
		private $acache_backend = '';
		private $button_flush;
		private $button_precache;
		protected $select_cache_type = array ();
		protected $select_invalidation_method = array ();
		protected $valid_cache_type = array ();
		private $precache_logfile = '';
		private $shell_function = false;
		private $shell_possibilities = array ();


		/**
		 * init hook function runs before admin panel hook, themeing and options read
		 */
		public function plugin_init() {
			/* advanced cache "worker" file */
			$this->acache_worker = $this->plugin_dir . $this->plugin_constant . '-acache.php';
			/* WordPress advanced-cache.php file location */
			$this->acache = WP_CONTENT_DIR . '/advanced-cache.php';
			/* nginx sample config file */
			$this->nginx_sample = $this->plugin_dir . $this->plugin_constant . '-nginx-sample.conf';
			/* backend driver file */
			$this->acache_backend = $this->plugin_dir . $this->plugin_constant . '-backend.php';
			/* flush button identifier */
			$this->button_flush = $this->plugin_constant . '-flush';
			/* precache button identifier */
			$this->button_precache = $this->plugin_constant . '-precache';
			/* global options identifier */
			$this->global_option = $this->plugin_constant . '-global';
			/* precache log */
			$this->precache_logfile = sys_get_temp_dir() . '/' . self::precache_log;
			/* search for a system function */
			$this->shell_possibilities = array ( 'shell_exec', 'exec', 'system', 'passthru' );
			$disabled_functions = array_map('trim', explode(',', ini_get('disable_functions') ) );

			foreach ( $this->shell_possibilities as $possible ) {
				if ( function_exists ($possible) && ! ( ini_get('safe_mode') || in_array( $possible, $disabled_functions ) ) ) {
					/* set shell function */
					$this->shell_function = $possible;
					break;
				}
			}

			/* set global config key; here, because it's needed for migration */
			if ( $this->network )
				$this->global_config_key = 'network';
			else
				$this->global_config_key = $_SERVER['HTTP_HOST'];

			/* cache type possible values array */
			$this->select_cache_type = array (
				'apc' => __( 'APC' , $this->plugin_constant ),
				'memcache' => __( 'PHP Memcache' , $this->plugin_constant ),
				'memcached' => __( 'PHP Memcached' , $this->plugin_constant ),
			);

			$this->valid_cache_type = array (
				'apc' => function_exists( 'apc_sma_info' ) ? true : false,
				'memcache' => class_exists ( 'Memcache') ? true : false,
				'memcached' => class_exists ( 'Memcached') ? true : false,
			);

			/* invalidation method possible values array */
			$this->select_invalidation_method = array (
				0 => __( 'flush cache' , $this->plugin_constant ),
				1 => __( 'only modified post' , $this->plugin_constant ),
				2 => __( 'modified post and all taxonomies' , $this->plugin_constant ),
			);

		}

		/**
		 * additional init, steps that needs the plugin  options
		 *
		 */
		public function plugin_setup () {

			/* initiate backend */
			$this->backend = new WP_FFPC_Backend ( $this->options, $this->network );

			/* get all available post types */
			$post_types = get_post_types( );

			/* cache invalidation hooks */
			foreach ( $post_types as $post_type ) {
				add_action( 'new_to_publish_' .$post_type , array( &$this->backend , 'clear' ), 0 );
				add_action( 'draft_to_publish' .$post_type , array( &$this->backend , 'clear' ), 0 );
				add_action( 'pending_to_publish' .$post_type , array( &$this->backend , 'clear' ), 0 );
				add_action( 'private_to_publish' .$post_type , array( &$this->backend , 'clear' ), 0 );
				add_action( 'publish_' . $post_type , array( &$this->backend , 'clear' ), 0 );
			}

			/* invalidation on some other ocasions as well */
			add_action( 'switch_theme', array( &$this->backend , 'clear' ), 0 );
			add_action( 'deleted_post', array( &$this->backend , 'clear' ), 0 );
			add_action( 'edit_post', array( &$this->backend , 'clear' ), 0 );

			/* add filter for catching canonical redirects */
			add_filter('redirect_canonical', 'wp_ffpc_redirect_callback', 10, 2);

		}

		/**
		 * activation hook function, to be extended
		 */
		public function plugin_activate() {
			/* we leave this empty to avoid not detecting WP network correctly */
		}

		/**
		 * deactivation hook function, to be extended
		 */
		public function plugin_deactivate () {
			/* remove current site config from global config */
			$this->update_global_config( true );
		}

		/**
		 * uninstall hook function, to be extended
		 */
		public function plugin_uninstall( $delete_options = true ) {
			/* delete advanced-cache.php file */
			unlink ( $this->acache );

			/* delete site settings */
			if ( $delete_options ) {
				$this->plugin_options_delete ();
			}
		}

		/**
		 * extending admin init
		 *
		 */
		public function plugin_hook_admin_init () {
			/* save parameter updates, if there are any */
			if ( isset( $_POST[ $this->button_flush ] ) ) {
				$this->backend->clear( false, true );
				$this->status = 3;
				header( "Location: ". $this->settings_link . self::slug_flush );
			}

			/* save parameter updates, if there are any */
			if ( isset( $_POST[ $this->button_precache ] ) ) {

				if ( $this->shell_function == false ) {
					$this->status = 5;
					header( "Location: ". $this->settings_link . self::slug_precache_disabled );
				}
				else {
					$this->precache_message = $this->precache();
					$this->status = 4;
					header( "Location: ". $this->settings_link . self::slug_precache );
				}
			}
		}

		/**
		 * admin panel, the admin page displayed for plugin settings
		 */
		public function plugin_admin_panel() {
			/**
			 * security, if somehow we're running without WordPress security functions
			 */
			if( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ){
				die( );
			}
			?>

			<div class="wrap">

			<script>
				jQuery(document).ready(function($) {
					jQuery( "#<?php echo $this->plugin_constant ?>-settings" ).tabs();
					jQuery( "#<?php echo $this->plugin_constant ?>-commands" ).tabs();
				});
			</script>

			<?php

			$this->plugin_donation_form();

			/**
			 * if options were saved, display saved message
			 */
			if ( ! empty( $this->broadcast_message ) ) : ?>
				<div class="updated"><?php echo $this->broadcast_message; ?></div>
			<?php endif;

			/**
			 * if options were saved, display saved message
			 */
			if (isset($_GET[ self::key_save ]) && $_GET[ self::key_save ]=='true' || $this->status == 1) : ?>
				<div class='updated settings-error'><p><strong><?php _e( 'Settings saved.' , $this->plugin_constant ) ?></strong></p></div>
			<?php endif;

			/**
			 * if options were delete, display delete message
			 */
			if (isset($_GET[ self::key_delete ]) && $_GET[ self::key_delete ]=='true' || $this->status == 2) : ?>
				<div class='error'><p><strong><?php _e( 'Plugin options deleted.' , $this->plugin_constant ) ?></strong></p></div>
			<?php endif;

			/**
			 * if options were saved
			 */
			if (isset($_GET[ self::key_flush ]) && $_GET[ self::key_flush ]=='true' || $this->status == 3) : ?>
				<div class='updated settings-error'><p><strong><?php _e( "Cache flushed." , $this->plugin_constant ); ?></strong></p></div>
			<?php endif;

			/**
			 * if options were saved, display saved message
			 */
			if ( ( isset($_GET[ self::key_precache ]) && $_GET[ self::key_precache ]=='true' ) || $this->status == 4) : ?>
			<div class='updated settings-error'><p><strong><?php _e( 'Precache process was started, it is now running in the background, please be patient, it may take a very long time to finish.' , $this->plugin_constant ) ?></strong></p></div>
			<?php endif;

			/**
			 * the admin panel itself
			 */
			?>

			<h2><?php echo $this->plugin_name ; _e( ' settings', $this->plugin_constant ) ; ?></h2>

			<?php if ( ! WP_CACHE ) : ?>
				<div class="error"><p><?php _e("WP_CACHE is disabled, plugin will not work that way. Please add define `( 'WP_CACHE', true );` in wp-config.php", $this->plugin_constant ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! $this->global_saved ) : ?>
				<div class="error"><p><?php _e("WARNING: plugin settings are not yet saved for the site, please save settings!", $this->plugin_constant); ?></p><p><?php _e( "Technical information: the configuration array is not present in the global configuration." , $this->plugin_constant ) ?></p></div>
			<?php endif; ?>

			<?php if ( ! file_exists ( $this->acache ) ) : ?>
				<div class="error"><p><?php _e("WARNING: advanced cache file is yet to be generated, please save settings!", $this->plugin_constant); ?></p><p><?php _e( "Technical information: please check if location is writable: " . $this->acache , $this->plugin_constant ) ?></p></div>
			<?php endif; ?>

			<?php if ( $this->options['cache_type'] == 'memcached' && !class_exists('Memcached') ) : ?>
				<div class="error"><p><?php _e('ERROR: Memcached cache backend activated but no PHP memcached extension was found.', $this->plugin_constant); ?></p></div>
			<?php endif; ?>

			<?php if ( $this->options['cache_type'] == 'memcache' && !class_exists('Memcache') ) : ?>
				<div class="error"><p><?php _e('ERROR: Memcache cache backend activated but no PHP memcache extension was found.', $this->plugin_constant); ?></p></div>
			<?php endif; ?>

			<?php
				/* get the current runtime configuration for memcache in PHP because Memcache in binary mode is really problematic */
				if ( extension_loaded ( 'memcache' )  )
				{
					$memcache_settings = ini_get_all( 'memcache' );
					if ( !empty ( $memcache_settings ) && $this->options['cache_type'] == 'memcache' )
					{
						$memcache_protocol = strtolower($memcache_settings['memcache.protocol']['local_value']);
						if ( $memcached_protocol == 'binary' ) :
						?>
						<div class="error"><p><?php _e('WARNING: Memcache extension is configured to use binary mode. This is very buggy and the plugin will most probably not work correctly. <br />Please consider to change either to ASCII mode or to Memcached extension.', $this->plugin_constant ); ?></p></div>
						<?php
						endif;
					}
				}
			?>
			<div class="updated">
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
			<form method="post" action="#" id="<?php echo $this->plugin_constant ?>-settings" class="plugin-admin">

				<ul class="tabs">
					<li><a href="#<?php echo $this->plugin_constant ?>-type" class="wp-switch-editor"><?php _e( 'Cache type', $this->plugin_constant ); ?></a></li>
					<li><a href="#<?php echo $this->plugin_constant ?>-debug" class="wp-switch-editor"><?php _e( 'Debug & in-depth', $this->plugin_constant ); ?></a></li>
					<li><a href="#<?php echo $this->plugin_constant ?>-exceptions" class="wp-switch-editor"><?php _e( 'Cache exceptions', $this->plugin_constant ); ?></a></li>
					<li><a href="#<?php echo $this->plugin_constant ?>-memcached" class="wp-switch-editor"><?php _e( 'Memcache(d)', $this->plugin_constant ); ?></a></li>
					<li><a href="#<?php echo $this->plugin_constant ?>-nginx" class="wp-switch-editor"><?php _e( 'nginx', $this->plugin_constant ); ?></a></li>
					<li><a href="#<?php echo $this->plugin_constant ?>-precachelog" class="wp-switch-editor"><?php _e( 'Precache log', $this->plugin_constant ); ?></a></li>
				</ul>

				<fieldset id="<?php echo $this->plugin_constant ?>-type">
				<legend><?php _e( 'Set cache type', $this->plugin_constant ); ?></legend>
				<dl>
					<dt>
						<label for="cache_type"><?php _e('Select backend', $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<select name="cache_type" id="cache_type">
							<?php $this->print_select_options ( $this->select_cache_type , $this->options['cache_type'], $this->valid_cache_type ) ?>
						</select>
						<span class="description"><?php _e('Select backend storage driver', $this->plugin_constant); ?></span>
					</dd>

					<dt>
						<label for="expire"><?php _e('Expiration time (ms)', $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="number" name="expire" id="expire" value="<?php echo $this->options['expire']; ?>" />
						<span class="description"><?php _e('Sets validity time of entry in seconds', $this->plugin_constant); ?></span>
					</dd>

					<dt>
						<label for="charset"><?php _e('Charset', $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="text" name="charset" id="charset" value="<?php echo $this->options['charset']; ?>" />
						<span class="description"><?php _e('Charset of HTML and XML (pages and feeds) data.', $this->plugin_constant); ?></span>
					</dd>

					<dt>
						<label for="invalidation_method"><?php _e('Cache invalidation method', $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<select name="invalidation_method" id="invalidation_method">
							<?php $this->print_select_options ( $this->select_invalidation_method , $this->options['invalidation_method'] ) ?>
						</select>
						<span class="description"><?php _e('Select cache invalidation method. <ol><li><em>flush cache</em> - clears everything in storage, <strong>including values set by other applications</strong></li><li><em>only modified post</em> - clear only the modified posts entry, everything else remains in cache</li><li><em>modified post and all taxonomies</em> - removes all taxonomy term cache ( categories, tags, home, etc ) and the modified post as well</li></ol>', $this->plugin_constant); ?></span>
					</dd>

					<dt>
						<label for="prefix_data"><?php _e('Data prefix', $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="text" name="prefix_data" id="prefix_data" value="<?php echo $this->options['prefix_data']; ?>" />
						<span class="description"><?php _e('Prefix for HTML content keys, can be used in nginx. If you are caching with nginx, you should update your nginx configuration and restart nginx after changing this value.', $this->plugin_constant); ?></span>
					</dd>

					<dt>
						<label for="prefix_meta"><?php _e('Meta prefix', $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="text" name="prefix_meta" id="prefix_meta" value="<?php echo $this->options['prefix_meta']; ?>" />
						<span class="description"><?php _e('Prefix for meta content keys, used only with PHP processing.', $this->plugin_constant); ?></span>
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
					</dd>

					<dt>
						<label for="log_info"><?php _e("Enable information log", $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="log_info" id="log_info" value="1" <?php checked($this->options['log_info'],true); ?> />
						<span class="description"><?php _e('Enables INFO level messages; carefull, plugin is really talkative. Requires PHP syslog function.', $this->plugin_constant); ?></span>
					</dd>

					<dt>
						<label for="response_header"><?php _e("Add X-Cache-Engine header", $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="response_header" id="response_header" value="1" <?php checked($this->options['response_header'],true); ?> />
						<span class="description"><?php _e('Add X-Cache-Engine HTTP header to HTTP responses.', $this->plugin_constant); ?></span>
					</dd>

					<dt>
						<label for="generate_time"><?php _e("Add HTML debug comment", $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="generate_time" id="generate_time" value="1" <?php checked($this->options['generate_time'],true); ?> />
						<span class="description"><?php _e('Adds comment string including plugin name, cache engine and page generation time to every generated entry before closing <body> tag.', $this->plugin_constant); ?></span>
					</dd>

					<dt>
						<label for="sync_protocols"><?php _e("Enable sync protocolls", $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="sync_protocols" id="sync_protocols" value="1" <?php checked($this->options['sync_protocols'],true); ?> />
						<span class="description"><?php _e('Enable to replace every protocol to the same as in the request for site\'s domain', $this->plugin_constant); ?></span>
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
					</dd>

					<dt>
						<label for="nocache_home"><?php _e("Don't cache home", $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="nocache_home" id="nocache_home" value="1" <?php checked($this->options['nocache_home'],true); ?> />
						<span class="description"><?php _e('Exclude home page from caching', $this->plugin_constant); ?></span>

					</dd>

					<dt>
						<label for="nocache_feed"><?php _e("Don't cache feeds", $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="nocache_feed" id="nocache_feed" value="1" <?php checked($this->options['nocache_feed'],true); ?> />
						<span class="description"><?php _e('Exclude feeds from caching.', $this->plugin_constant); ?></span>
					</dd>

					<dt>
						<label for="nocache_archive"><?php _e("Don't cache archives", $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="nocache_archive" id="nocache_archive" value="1" <?php checked($this->options['nocache_archive'],true); ?> />
						<span class="description"><?php _e('Exclude archives from caching.', $this->plugin_constant); ?></span>
					</dd>

					<dt>
						<label for="nocache_single"><?php _e("Don't cache posts (and single-type entries)", $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="nocache_single" id="nocache_single" value="1" <?php checked($this->options['nocache_single'],true); ?> />
						<span class="description"><?php _e('Exclude singles from caching.', $this->plugin_constant); ?></span>
					</dd>

					<dt>
						<label for="nocache_page"><?php _e("Don't cache pages", $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="nocache_page" id="nocache_page" value="1" <?php checked($this->options['nocache_page'],true); ?> />
						<span class="description"><?php _e('Exclude pages from caching.', $this->plugin_constant); ?></span>
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
					</dd>
					<dt>
						<label for="persistent"><?php _e('Persistent memcache connections', $this->plugin_constant); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="persistent" id="persistent" value="1" <?php checked($this->options['persistent'],true); ?> />
						<span class="description"><?php _e('Make all memcache(d) connections persistent. Be carefull with this setting, always test the outcome.', $this->plugin_constant); ?></span>
					</dd>
				</dl>
				</fieldset>

				<fieldset id="<?php echo $this->plugin_constant ?>-nginx">
				<legend><?php _e('Sample config for nginx to utilize the data entries', $this->plugin_constant); ?></legend>
				<pre><?php echo $this->nginx_example(); ?></pre>
				</fieldset>

				<fieldset id="<?php echo $this->plugin_constant ?>-precachelog">
				<legend><?php _e('Log from previous pre-cache generation', $this->plugin_constant); ?></legend>
					<?php
					if ( @file_exists ( $this->precache_logfile ) ) :
						$log = file ( $this->precache_logfile );
						$head = explode( "\t", array_shift( $log ));
						$gentime = filemtime ( $this->precache_logfile );
					?>
					<p><strong><?php _e( 'Time of run: ') ?><?php echo date('r', $gentime ); ?></strong></p>
					<table style="width:100%; border: 1px solid #ccc;">
						<thead><tr>
							<th style="width:70%;"><?php echo $head[0]; ?></th>
							<th style="width:15%;"><?php echo $head[1]; ?></th>
							<th style="width:15%;"><?php echo $head[2]; ?></th>
						</tr></thead>
						<?php
						foreach ( $log as $line ) :
							$line = explode ( "\t", $line );
						?>
							<tr>
								<td><?php echo $line[0]; ?></td>
								<td><?php echo $line[1]; ?></td>
								<td><?php echo $line[2]; ?></td>
							</tr>
						<?php
						endforeach;
						?>
					</table>
				<?php
					else :
						_e('No precache log was found!', $this->plugin_constant);
					endif;
				?></pre>
				</fieldset>

				<p class="clear">
					<input class="button-primary" type="submit" name="<?php echo $this->button_save ?>" id="<?php echo $this->button_save ?>" value="<?php _e('Save Changes', $this->plugin_constant ) ?>" />
				</p>

			</form>

			<form method="post" action="#" id="<?php echo $this->plugin_constant ?>-commands" class="plugin-admin" style="padding-top:2em;">

				<ul class="tabs">
					<li><a href="#<?php echo $this->plugin_constant ?>-precache" class="wp-switch-editor"><?php _e( 'Precache', $this->plugin_constant ); ?></a></li>
					<li><a href="#<?php echo $this->plugin_constant ?>-flush" class="wp-switch-editor"><?php _e( 'Empty cache', $this->plugin_constant ); ?></a></li>
					<li><a href="#<?php echo $this->plugin_constant ?>-reset" class="wp-switch-editor"><?php _e( 'Reset settings', $this->plugin_constant ); ?></a></li>
				</ul>

				<fieldset id="<?php echo $this->plugin_constant ?>-precache">
				<legend><?php _e( 'Precache', $this->plugin_constant ); ?></legend>
				<dl>
					<dt>
						<?php if ( ( isset( $_GET[ self::key_precache_disabled ] ) && $_GET[ self::key_precache_disabled ] =='true' ) || $this->status == 5 || $this->shell_function == false ) : ?>
							<strong><?php _e( "Precache functionality is disabled due to unavailable system call function. <br />Since precaching may take a very long time, it's done through a background CLI process in order not to run out of max execution time of PHP. Please enable one of the following functions if you whish to use precaching: " , $this->plugin_constant ) ?><?php echo join( ',' , $this->shell_possibilities ); ?></strong>
						<?php else: ?>
							<input class="button-secondary" type="submit" name="<?php echo $this->button_precache ?>" id="<?php echo $this->button_precache ?>" value="<?php _e('Pre-cache', $this->plugin_constant ) ?>" />
						<?php endif; ?>
					</dt>
					<dd>
						<span class="description"><?php _e('Start a background process that visits all permalinks of all blogs it can found thus forces WordPress to generate cached version of all the pages.<br />The plugin tries to visit links of taxonomy terms without the taxonomy name as well. This may generate 404 hits, please be prepared for these in your logfiles if you plan to pre-cache.', $this->plugin_constant); ?></span>
					</dd>
				</dl>
				</fieldset>
				<fieldset id="<?php echo $this->plugin_constant ?>-flush">
				<legend><?php _e( 'Precache', $this->plugin_constant ); ?></legend>
				<dl>
					<dt>
						<input class="button-warning" type="submit" name="<?php echo $this->button_flush ?>" id="<?php echo $this->button_flush ?>" value="<?php _e('Clear cache', $this->plugin_constant ) ?>" />
					</dt>
					<dd>
						<span class="description"><?php _e ( "Clear all entries in the storage, including the ones that were set by other processes.", $this->plugin_constant ); ?> </span>
					</dd>
				</dl>
				</fieldset>
				<fieldset id="<?php echo $this->plugin_constant ?>-reset">
				<legend><?php _e( 'Precache', $this->plugin_constant ); ?></legend>
				<dl>
					<dt>
						<input class="button-warning" type="submit" name="<?php echo $this->button_delete ?>" id="<?php echo $this->button_delete ?>" value="<?php _e('Reset options', $this->plugin_constant ) ?>" />
					</dt>
					<dd>
						<span class="description"><?php _e ( "Reset settings to defaults.", $this->plugin_constant ); ?> </span>
					</dd>
				</dl>
				</fieldset>
			</form>
			</div>
			<?php
		}

		/**
		 * extending options_save
		 *
		 */
		public function plugin_hook_options_save( $activating ) {

			/* flush the cache when news options are saved, not needed on activation */
			if ( !$activating )
				$this->backend->clear();

			/* create the to-be-included configuration for advanced-cache.php */
			$this->update_global_config();

			/* create advanced cache file, needed only once or on activation, because there could be lefover advanced-cache.php from different plugins */
			if (  !$activating )
				$this->deploy_advanced_cache();

		}

		/**
		 * read hook; needs to be implemented
		 */
		public function plugin_hook_options_read( &$options ) {
			/* read the global options, network compatibility */
			$this->global_config = get_site_option( $this->global_option );

			/* check if current site present in global config */
			if ( !empty ( $this->global_config[ $this->global_config_key ] ) )
				$this->global_saved = true;

			$this->global_config[ $this->global_config_key ] = $options;
		}

		/**
		 * options delete hook; needs to be implemented
		 */
		public function plugin_hook_options_delete(  ) {
			delete_site_option ( $this->global_option );
		}

		/**
		 * need to do migrations from previous versions of the plugin
		 *
		 */
		public function plugin_hook_options_migrate( &$options ) {
			$migrated = false;

			if ( $options['version'] != $this->plugin_version || !isset ( $options['version'] ) ) {

				/* cleanup possible leftover files from previous versions */
				$check = array ( 'advanced-cache.php', 'nginx-sample.conf', 'wp-ffpc.admin.css', 'wp-ffpc-common.php' );
				foreach ( $check as $fname ) {
					$fname = $this->plugin_dir . $fname;
					if ( file_exists ( $fname ) )
						unlink ( $fname );
				}

				/* look for previous config leftovers */
				$try = get_site_option( $this->plugin_constant );
				/* network option key changed, remove & migrate the leftovers if there's any */
				if ( !empty ( $try ) && $this->network ) {
					/* clean it up, we don't use it anymore */
					delete_site_option ( $this->plugin_constant );

					if ( empty ( $options ) && array_key_exists ( $this->global_config_key, $try ) ) {
						$options = $try [ $this->global_config_key ];
						$migrated = true;
					}
					elseif ( empty ( $options ) && array_key_exists ( 'host', $try ) ) {
						$options = $try;
						$migrated = true;
					}
				 }

				/* updating from version <= 0.4.x */
				if ( !empty ( $options['host'] ) ) {
					$options['hosts'] = $options['host'] . ':' . $options['port'];
					$migrated = true;
				}
				/* migrating from version 0.6.x */
				elseif ( is_array ( $options ) && array_key_exists ( $this->global_config_key , $options ) ) {
					$options = $options[ $this->global_config_key ];
					$migrated = true;
				}
				/* migrating from something, drop previous config */
				else {
					$options = array();
				}

				if ( $migrated ) {
					/* renamed options */
					if ( isset ( $options['syslog'] ) )
						$options['log'] = $options['syslog'];
					if ( isset ( $options['debug'] ) )
					$options['response_header'] = $options['debug'];
				}

			}
		}

		/**
		 * advanced-cache.php creator function
		 *
		 */
		private function deploy_advanced_cache( ) {

			/* in case advanced-cache.php was already there, remove it */
			if ( @file_exists( $this->acache ))
				unlink ($this->acache);

			/* is deletion was unsuccessful, die, we have no rights to do that, fail */
			if ( @file_exists( $this->acache ))
				return false;

			/* if no active site left no need for advanced cache :( */
			if ( empty ( $this->global_config ) )
				return false;

			/* add the required includes and generate the needed code */
			$string[] = "<?php";
			$string[] = self::global_config_var . ' = ' . var_export ( $this->global_config, true ) . ';' ;
			$string[] = "include_once ('" . $this->acache_backend . "');";
			$string[] = "include_once ('" . $this->acache_worker . "');";
			$string[] = "?>";

			/* write the file and start caching from this point */
			return file_put_contents( $this->acache, join( "\n" , $string ) );
		}

		/**
		 * function to generate working example from the nginx sample file
		 *
		 * @return string nginx config file
		 *
		 */
		private function nginx_example () {
			/* read the sample file */
			$nginx = file_get_contents ( $this->nginx_sample );

			/* this part is not used when the cache is turned on for logged in users */
			$loggedin = '# avoid cache for logged in users
				if ($http_cookie ~* "comment_author_|wordpressuser_|wp-postpass_" ) {
					set $memcached_request 0;
				}';

			/* replace the data prefix with the configured one */
			$to_replace = array ( 'DATAPREFIX' , 'SERVERROOT', 'SERVERLOG' );
			$replace_with = array ( $this->options['prefix_data'], ABSPATH, $_SERVER['SERVER_NAME'] );
			$nginx = str_replace ( $to_replace , $replace_with , $nginx );

			/* set upstream servers from configured servers, best to get from the actual backend */
			$servers = $this->backend->get_servers();
			$nginx_servers = '';
			foreach ( array_keys( $servers ) as $server ) {
				$nginx_servers .= "		server ". $server .";\n";
			}
			$nginx = str_replace ( 'MEMCACHED_SERVERS' , $nginx_servers , $nginx );

			/* add logged in cache, if valid */
			if ( ! $this->options['cache_loggedin'])
				$nginx = str_replace ( 'LOGGEDIN_EXCEPTION' , $loggedin , $nginx );
			else
				$nginx = str_replace ( 'LOGGEDIN_EXCEPTION' , '' , $nginx );

			return $nginx;
		}

		/**
		 * function to update global configuration
		 *
		 * @param boolean $remove_site Bool to remove or add current config to global
		 *
		 */
		private function update_global_config ( $remove_site = false ) {

			/* remove or add current config to global config */
			if ( $remove_site ) {
				unset ( $this->global_config[ $this->global_config_key ] );
			}
			else {
				$this->global_config[ $this->global_config_key ] = $this->options;
			}

			/* deploy advanced-cache.php */
			$this->deploy_advanced_cache ();

			/* save options to database */
			update_site_option( $this->global_option , $this->global_config );
		}

		/**
		 * generate cache entry for every available permalink, might be very-very slow,
		 * therefore it starts a background process
		 *
		 */
		private function precache () {
			/* container for links to precache, well be accessed by reference */
			$links = array();

			/* when plugin is  network wide active, we need to pre-cache for all link of all blogs */
			if ( $this->network ) {
				/* list all blogs */
				$blog_list = get_blog_list( 0, 'all' );
				foreach ($blog_list as $blog) {
					/* get permalinks for this blog */
					$this->precache_list_permalinks ( $links, $blog['blog_id'] );
				}
			}
			else {
				/* no network, better */
				$this->precache_list_permalinks ( $links, false );
			}

			/* temporary php file, will destroy itself after finish in order to clean up */
			$tmpfile = tempnam(sys_get_temp_dir(), 'wp-ffpc');

			/* double check if we do have any links to pre-cache */
			if ( !empty ( $links ) ) :

			/* this is the precacher php worker file: logs the links, their generation time and the generated content size
			* writes the logfile and destroys itself afterwards
			*/
			$out .= '<?php
				$links = ' . var_export ( $links , true ) . ';

				echo "permalink\tgeneration time (s)\tsize ( kbyte )\n";
				foreach ( $links as $permalink => $dummy ) {
					$starttime = explode ( " ", microtime() );
					$starttime = $starttime[1] + $starttime[0];

						$page = file_get_contents( $permalink );
						$size = round ( ( strlen ( $page ) / 1024 ), 3 );

					$endtime = explode ( " ", microtime() );
					$endtime = ( $endtime[1] + $endtime[0] ) - $starttime;

					echo $permalink . "\t" . $endtime . "\t" . $size . "\n";
					unset ( $page, $size, $starttime, $endtime );
					sleep( 1 );
				}
				unlink ( "'. $tmpfile .'" );
			?>';

			file_put_contents ( $tmpfile, $out  );
			/* call the precache worker file in the background */
			$shellfunction = $this->shell_function;
			$shellfunction( 'php '. $tmpfile .' >'. $this->precache_logfile .' 2>&1 &' );

			endif;
		}


		/**
		 * gets all post-like entry permalinks for a site, returns values in passed-by-reference array
		 *
		 */
		private function precache_list_permalinks ( &$links, $site = false ) {
			/* $post will be populated when running throught the posts */
			global $post;
			include_once ( ABSPATH . "wp-load.php" );

			/* if a site id was provided, save current blog and change to the other site */
			if ( $site !== false ) {
				$current_blog = get_current_blog_id();
				switch_to_blog( $site );

				$url = get_blog_option ( $site, 'siteurl' );
				if ( substr( $url, -1) !== '/' )
					$url = $url . '/';

				$links[ $url ] = true;
			}


			/* get all published posts */
			$args = array (
				'post_type' => 'any',
				'posts_per_page' => -1,
				'post_status' => 'publish',
			);
			$posts = new WP_Query( $args );

			/* get all the posts, one by one  */
			while ( $posts->have_posts() ) {
				$posts->the_post();

				/* get the permalink for currently selected post */
				switch ($post->post_type) {
					case 'revision':
					case 'nav_menu_item':
						break;
					case 'page':
						$permalink = get_page_link( $post->ID );
						break;
					case 'post':
						$permalink = get_permalink( $post->ID );
						break;
					case 'attachment':
						$permalink = get_attachment_link( $post->ID );
						break;
					default:
						$permalink = get_post_permalink( $post->ID );
					break;
				}

				/* collect permalinks */
				$links[ $permalink ] = true;

			}

			//$this->taxonomy_links ( $links );
			$this->backend->taxonomy_links ( $links );

			/* just in case, reset $post */
			wp_reset_postdata();

			/* switch back to original site if we navigated away */
			if ( $site !== false ) {
				switch_to_blog( $current_blog );
			}
		}

	}
}

?>
