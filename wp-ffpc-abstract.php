<?php
/* abstact plugin base class */

if (!class_exists('WP_Plugins_Abstract')) {

	/**
	 * abstract class for common, required functionalities
	 *
	 * @var string $plugin_constant The name of the plugin, will be used with strings, names, etc.
	 * @var array $options Plugin options array
	 * @var array $defaults Default options array
	 * @var int $status Save, delete, neutral status storage
	 * @var boolean $network true if plugin is Network Active
	 * @var string $settings_link Link for settings page
	 * @var string $plugin_url URL of plugin directory to be used with url-like includes
	 * @var string $plugin_dir Directory of plugin to be used with standard includes
	 * @var string $plugin_file Filename of main plugin PHP file
	 * @var string $plugin_name Name of the plugin
	 * @var string $plugin_version Plugin version number
	 * @var string $plugin_option_group Option group name of plugin
	 * @var string $setting_page Setting page URL name
	 * @var string $setting_slug Parent settings page slug
	 * @var string $donation_link Donation link URL
	 * @var string $button_save ID of save button in HTML form
	 * @var string $button_delete ID of delete button in HTML form
	 * @var int $capability Level of admin required to manage plugin settings
	 * @var string $slug_save URL slug to present saved state
	 * @var string $slug_delete URL slug to present delete state
	 * @var int $loglevel Level of log in syslog
	 * @var boolean $debug Enables syslog messages if true
	 *
	 */
	abstract class WP_Plugins_Abstract {

		private $plugin_constant;
		private $options = array();
		private $defaults = array();
		private $status = 0;
		private $network = false;
		private $settings_link = '';
		private $settings_slug = '';
		private $plugin_url;
		private $plugin_dir;
		private $plugin_file;
		private $plugin_name;
		private $plugin_version;
		private $plugin_option_group;
		private $plugin_settings_page;
		private $donation_link;
		private $button_save;
		private $button_delete;
		public $capability = 10;
		const slug_save = '&saved=true';
		const slug_delete = '&deleted=true';
		public $debug = false;

		/**
		* constructor
		*
		* @param string $plugin_constant General plugin identifier, same as directory & base PHP file name
		* @param string $plugin_version Version number of the parameter
		* @param mixed $defaults Default value(s) for plugin option(s)
		* @param string $donation_link Donation link of plugin
		*
		*/
		private function __construct( $plugin_constant, $plugin_version, $plugin_name, $defaults, $donation_link ) {

			$this->plugin_constant = $plugin_constant;
			$this->plugin_url = $this->replace_if_ssl ( get_option( 'siteurl' ) ) . '/wp-content/plugins/' . $this->plugin_constant . '/';
			$this->plugin_dir = ABSPATH . 'wp-content/plugins/' . $this->plugin_constant . '/';
			$this->plugin_file = $this->plugin_constant . '/' . $this->plugin_constant . '.php';
			$this->plugin_version = $plugin_version;
			$this->plugin_name = $plugin_name;
			$this->defaults = $defaults;
			$this->plugin_option_group = $this->plugin_constant .' -params';
			$this->plugin_settings_page = $this->plugin_constant .' -settings';
			$this->donation_link = $donation_link;
			$this->button_save = $this->plugin_constant . '-save';
			$this->button_delete = $this->plugin_constant . '-delete';

			/* we need network wide plugin check functions */
			if ( ! function_exists( 'is_plugin_active_for_network' ) )
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

			/* check if plugin is network-activated */
			if ( @is_plugin_active_for_network ( $this->plugin_file ) )
			{
				$this->network = true;
				$this->settings_slug = 'settings.php';
			}
			else
			{
				$this->settings_slug = 'options-general.php';
			}

			$this->settings_link = $this->settings_slug . '?page=' .  $this->plugin_settings_page;

			/* register options on very first run
			 * this will only register parameter once
			 */
			add_site_option( $this->plugin_constant );

			/* get the options */
			$this->plugin_options_read();

			/* initialize plugin, plugin specific init functions */
			$this->plugin_init();

			/* add admin styling */
			if( is_admin() )
			{
				/* jquery ui tabs is provided by WordPress */
				wp_enqueue_script ( "jquery-ui-tabs" );

				/* additional admin styling */
				$css_handle = $this->plugin_constant . '-admin-css';
				$css_file = $this->plugin_constant . '.admin.css';
				if ( @file_exists ( $this->plugin_dir . $css_file ) )
				{
					$css_src = $this->plugin_url . $css_file;
					wp_register_style( $css_handle, $css_src, false, false, 'all' );
					wp_enqueue_style( $css_handle );
				}
			}

			register_activation_hook(__FILE__ , array( $this , 'plugin_activate') );
			register_deactivation_hook(__FILE__ , array( $this , 'plugin_deactivate') );
			register_uninstall_hook(__FILE__ , array( $this , 'plugin_uninstall') );

			/* register settings pages */
			if ( $this->network )
				add_filter( "network_admin_plugin_action_links_" . $this->plugin_file, array( $this, 'settings_link' ) );
			else
				add_filter( "plugin_action_links_" . $this->plugin_file, array( $this, 'settings_link' ) );

			/* register admin init */
			if ( $this->network )
				add_action('network_admin_menu', array( $this , 'plugin_admin') );
			else
				add_action('admin_menu', array( $this , 'plugin_admin') );
		}

		/**
		 * activation hook function, to be extended
		 */
		abstract function plugin_activate();

		/**
		 * deactivation hook function, to be extended
		 */
		abstract function plugin_deactivate ();

		/**
		 * uninstall hook function, to be extended
		 */
		abstract function plugin_uninstall();

		/**
		 * init hook function, to be extended, runs before admin panel hook & theming activated
		 */
		abstract function plugin_init();

		/**
		 * admin panel, the HTML usually
		 */
		abstract function plugin_admin_panel();

		/**
		 * admin init: save/delete setting, add admin panel call hook
		 */
		private function plugin_admin_init() {

			/* save parameter updates, if there are any */
			if ( isset( $_POST[ $this->button_save ] ) )
			{
				$this->plugin_options_save();
				$this->status = 1;
				header( "Location: ". $this->settings_link . self::slug_save );
			}

			/* save parameter updates, if there are any */
			if ( isset( $_POST[ $this->button_delete ] ) )
			{
				$this->plugin_options_delete();
				$this->status = 2;
				header( "Location: ". $this->settings_link . self::slug_delete );
			}

			add_submenu_page( $this->settings_slug, $this->plugin_name . __( ' options' , $this->plugin_constant ), $this->plugin_name, $this->capability, $this->plugin_settings_page, $function, array ( $this , 'plugin_admin_panel' ) );
		}

		/**
		 * deletes saved options from database
		 */
		private function plugin_options_delete () {
			delete_site_option( $this->plugin_constant );

			/* additional moves */
			$this->plugin_hook_options_delete();
		}

		/**
		 * hook to add functionality into plugin_options_read
		 */
		abstract function plugin_hook_options_delete ();

		/**
		 * reads options stored in database and reads merges them with default values
		 */
		private function plugin_options_read () {
			/* get the currently saved options */
			$options = get_site_option( $this->plugin_constant );

			/* map missing values from default */
			foreach ( $this->defaults as $key => $default )
				if ( !array_key_exists ( $key, $options ) )
					$options[$key] = $default;

			/* removed unused keys, rare, but possible */
			foreach ( array_keys ( $options ) as $key )
				if ( !array_key_exists( $key, $this->defaults ) )
					unset ( $options[$key] );

			$this->plugin_hook_options_read();
		}

		/**
		 * hook to add functionality into plugin_options_read
		 */
		abstract function plugin_hook_options_read ();

		/**
		 * used on update and to save current options to database
		 *
		 * @param boolean $activating [optional] true on activation hook
		 *
		 * @return
		 */
		private function plugin_options_save ( $activating = false ) {

			/* only try to update defaults if it's not activation hook, $_POST is not empty and the post
			   is ours */
			if ( !$activating && !empty ( $_POST ) && isset( $_POST[ $this->button_save ] ) )
			{
				/* we'll only update those that exist in the defaults array */
				$options = $this->defaults;

				foreach ( $options as $key => $default )
				{
					/* $_POST element is available */
					if ( !empty( $_POST[$key] ) )
					{
						$update = $_POST[$key];

						/* get rid of slashes in strings, just in case */
						if ( is_string ( $update ) )
							$update = stripslashes($update);

						$options[$key] = $update;
					}
					/* empty $_POST element: when HTML form posted, empty checkboxes a 0 input
					   values will not be part of the $_POST array, thus we need to check
					   if this is the situation by checking the types of the elements,
					   since a missing value means update from an integer to 0
					*/
					elseif ( empty( $_POST[$key] ) && ( is_bool ( $default ) || is_int( $default ) ) )
					{
						$options[$key] = 0;
					}
				}

				/* update the options array */
				$this->options = $options;
			}

			/* set plugin version */
			$this->options['version'] = $this->plugin_version;

			/* call hook function for additional moves before saving the values */
			$this->plugin_hook_options_save( $activating );

			/* save options to database */
			update_site_option( $this->plugin_constant , $this->options );
		}

		/**
		 * hook to add functionality into plugin_options_save
		 */
		abstract function plugin_hook_options_save ( $activating );

		/**
		 * sends message to sysog
		 *
		 * @param string $message message to add besides basic info
		 *
		 */

		private function log ( $message, $log_level = LOG_INFO ) {

			if ( @is_array( $message ) || @is_object ( $message ) )
				$message = serialize($message);

			if (! $this->config['log'] )
				return false;

			switch ( $log_level ) {
				case LOG_ERR :
					if ( function_exists( 'syslog' ) )
						syslog( $log_level , self::plugin_constant . $message );
					/* error level is real problem, needs to be displayed on the admin panel */
					throw new Exception ( $message );
				break;
				default:
					if ( function_exists( 'syslog' ) && $this->config['debug'] )
						syslog( $log_level , self::plugin_constant . $message );
				break;
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
		private function replace_if_ssl ( $url ) {
			if ( isset($_SERVER['HTTPS']) && ( ( strtolower($_SERVER['HTTPS']) == 'on' )  || ( $_SERVER['HTTPS'] == '1' ) ) )
				$url = str_replace ( 'http://' , 'https://' , $url );

			return $url;
		}

		/**
		 * function to easily print a variable
		 *
		 * @param mixed $var Variable to dump
		 * @param boolean $ret Return text instead of printing if true
		 *
		*/
		private function print_var ( $var , $ret = false ) {
			if ( @is_array ( $var ) || @is_object( $var ) || @is_bool( $var ) )
				$var = var_export ( $var, true );

			if ( $ret )
				return $var;
			else
				echo $var;
		}

		/**
		 * print value of an element from defaults array
		 *
		 * @param mixed $e Element index of $this->defaults array
		 *
		 */
		private function print_default ( $e ) {
			_e('Default : ', $this->plugin_constant);
			print_var ( $this->defaults[ $e ] );
		}

		/**
		 * select field processor
		 *
		 * @param sizes
		 * 	array to build <option> values of
		 *
		 * @param $current
		 * 	the current resize type
		 *
		 * @param $returntext
		 * 	boolean: is true, the description will be returned of $current type
		 *
		 * @return
		 * 	prints either description of $current
		 * 	or option list for a <select> input field with $current set as active
		 *
		 */
		private function print_select_options ( $elements, $current ) {
			foreach ($elements as $value => $name ) : ?>
				<option value="<?php echo $value ?>" <?php selected( $value , $current ); ?>>
					<?php echo $name ; ?>
				</option>
			<?php endforeach;
		}

	}
}
