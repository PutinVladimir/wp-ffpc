<?php
/**
 * abstract base class of WP-* plugins from hello@petermolnar.eu
 * version 2
 */

if (!class_exists('WP_Plugins_Abstract_v2')) {

	include_once ( 'wp-plugin-utilities.php' );
	/**
	 * abstract class for common, required functionalities
	 *
	 * @var string $plugin_constant The name of the plugin, will be used with strings, names, etc.
	 * @var array $options Plugin options array
	 * @var array $defaults Default options array
	 * @var int $status Save, delete, neutral status storage
	 * @var boolean $network true if plugin is Network Active
	 * @var string $settings_link Link for settings page
	 * @var string $common_url URL of plugin common files directory
	 * @var string $common_dir Directory of plugin common files
	 * @var string $plugin_url URL of plugin directory to be used with url-like includes
	 * @var string $plugin_dir Directory of plugin to be used with standard includes
	 * @var string $plugin_file Filename of main plugin PHP file
	 * @var string $plugin_name Name of the plugin
	 * @var string $plugin_version Plugin version number
	 * @var string $setting_page Setting page URL name
	 * @var string $setting_slug Parent settings page slug
	 * @var string $donation_link Donation link URL
	 * @var string $button_save ID of save button in HTML form
	 * @var string $button_delete ID of delete button in HTML form
	 * @var int $capability Level of admin required to manage plugin settings
	 * @var string $slug_save URL slug to present saved state
	 * @var string $slug_delete URL slug to present delete state
	 * @var string $broadcast_url URL base of broadcast messages
	 * @var string donation_business_id Business ID for donation form
	 * @var string $donation_business_name Business name for donation form
	 * @var string $donation_item_name Donation item name for donation form
	 * @var string donation_business_id Business ID for donation form
	 * @var string $broadcast_message Name of the file to get broadcast message from the web
	 *
	 */
	abstract class WP_Plugins_Abstract_v2 {

		const slug_save = '&saved=true';
		const slug_delete = '&deleted=true';
		const broadcast_url = 'http://petermolnar.eu/broadcast/';
		const donation_business_id = 'FA3NT7XDVHPWU';
		const common_slug = '/wp-common';

		protected $plugin_constant;
		protected $options = array();
		protected $defaults = array();
		protected $status = 0;
		protected $network = false;
		protected $settings_link = '';
		protected $settings_slug = '';
		protected $plugin_url;
		protected $plugin_dir;
		protected $common_url;
		protected $common_dir;
		protected $plugin_file;
		protected $plugin_name;
		protected $plugin_version;
		protected $plugin_settings_page;
		protected $donation_link;
		protected $button_save;
		protected $button_delete;
		protected $capability = 'manage_options';
		protected $donation_business_name;
		protected $donation_item_name;
		protected $broadcast_message;
		protected $admin_css_handle;
		protected $admin_css_url;
		protected $utilities;


		/**
		* constructor
		*
		* @param string $plugin_constant General plugin identifier, same as directory & base PHP file name
		* @param string $plugin_version Version number of the parameter
		* @param string $plugin_name Readable name of the plugin
		* @param mixed $defaults Default value(s) for plugin option(s)
		* @param string $donation_link Donation link of plugin
		*
		*/
		public function __construct( $plugin_constant, $plugin_version, $plugin_name, $defaults, $donation_link ) {

			$this->plugin_constant = $plugin_constant;

			$this->common_url = plugin_dir_url(__FILE__);
			$this->common_dir = plugin_dir_path(__FILE__);

			$this->plugin_url = str_replace ( self::common_slug , '', $this->common_url );
			$this->plugin_dir = str_replace ( self::common_slug , '', $this->common_dir );

			$this->admin_css_handle = $this->plugin_constant . '-admin-css';
			$this->admin_css_url = $this->common_url . 'wp-admin.css';

			$this->plugin_file = $this->plugin_constant . '/' . $this->plugin_constant . '.php';
			$this->plugin_version = $plugin_version;
			$this->plugin_name = $plugin_name;
			$this->defaults = $defaults;
			$this->plugin_settings_page = $this->plugin_constant .'-settings';
			$this->donation_link = $donation_link;
			$this->button_save = $this->plugin_constant . '-save';
			$this->button_delete = $this->plugin_constant . '-delete';
			$this->broadcast_message = self::broadcast_url . $this->plugin_constant . '.message';
			$this->donation_business_name = 'PeterMolnar_WordPressPlugins_' . $this->plugin_constant . '_HU';
			$this->donation_item_name = $this->plugin_name;

			$this->utilities = WP_Plugins_Utilities_v1::Utility();

			/* we need network wide plugin check functions */
			if ( ! function_exists( 'is_plugin_active_for_network' ) )
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

			/* check if plugin is network-activated */
			if ( @is_plugin_active_for_network ( $this->plugin_file ) ) {
				$this->network = true;
				$this->settings_slug = 'settings.php';
			}
			else {
				$this->settings_slug = 'options-general.php';
			}

			/* set the settings page link string */
			$this->settings_link = $this->settings_slug . '?page=' .  $this->plugin_settings_page;

			add_action( 'init', array(&$this,'init'));

			add_action( 'admin_enqueue_scripts', array(&$this,'enqueue_admin_css_js'));

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
		 * first init hook function, to be extended, before options were read
		 */
		abstract function plugin_init();

		/**
		 * second init hook function, to be extended, after options were read
		 */
		abstract function plugin_setup();

		/**
		 * admin panel, the HTML usually
		 */
		abstract function plugin_admin_panel();

		/**
		 * admin help menu
		 */
		abstract function plugin_admin_help( $contextual_help, $screen_id );

		/**
		 * admin init called by WordPress add_action, needs to be public
		 */
		public function plugin_admin_init() {

			/* save parameter updates, if there are any */
			if ( isset( $_POST[ $this->button_save ] ) ) {
				$this->plugin_options_save();
				$this->status = 1;
				header( "Location: ". $this->settings_link . self::slug_save );
			}

			/* delete parameters if requested */
			if ( isset( $_POST[ $this->button_delete ] ) ) {
				$this->plugin_options_delete();
				$this->status = 2;
				header( "Location: ". $this->settings_link . self::slug_delete );
			}

			/* load additional moves */
			$this->plugin_hook_admin_init();

			/* get broadcast message, if available */
			$this->broadcast_message = @file_get_contents( $this->broadcast_message );

			/* add submenu to settings pages */
			add_submenu_page( $this->settings_slug, $this->plugin_name . __( ' options' , $this->plugin_constant ), $this->plugin_name, $this->capability, $this->plugin_settings_page, array ( &$this , 'plugin_admin_panel' ) );
		}

		/**
		 * to be extended
		 *
		 */
		abstract function plugin_hook_admin_init();


		public function init(){
			/* initialize plugin, plugin specific init functions */
			$this->plugin_init();

			/* get the options */
			$this->plugin_options_read();

			/* setup plugin, plugin specific setup functions that need options */
			$this->plugin_setup();

			register_activation_hook( $this->plugin_file , array( &$this, 'plugin_activate' ) );
			register_deactivation_hook( $this->plugin_file , array( &$this, 'plugin_deactivate' ) );
			/* uninstall moved to external file */
			//register_uninstall_hook( $this->plugin_file , array ( $this, 'plugin_uninstall' ) );

			/* register settings pages */
			if ( $this->network )
				add_filter( "network_admin_plugin_action_links_" . $this->plugin_file, array( &$this, 'plugin_settings_link' ) );
			else
				add_filter( "plugin_action_links_" . $this->plugin_file, array( &$this, 'plugin_settings_link' ) );

			/* register admin init, catches $_POST and adds submenu to admin menu */
			if ( $this->network )
				add_action('network_admin_menu', array( &$this , 'plugin_admin_init') );
			else
				add_action('admin_menu', array( &$this , 'plugin_admin_init') );

			add_filter('contextual_help', array( &$this, 'plugin_admin_help' ), 10, 2);
		}

		/**
		 * callback function to add settings link to plugins page
		 *
		 * @param array $links Current links to add ours to
		 *
		 */
		public function plugin_settings_link ( $links ) {
			$settings_link = '<a href="' . $this->settings_link . '">' . __( 'Settings', $this->plugin_constant ) . '</a>';
			array_unshift( $links, $settings_link );
			return $links;
		}

		/* add admin styling */
		public function enqueue_admin_css_js(){
			/* jquery ui tabs is provided by WordPress */
			wp_enqueue_script ( "jquery-ui-tabs" );
			wp_enqueue_script ( "jquery-ui-slider" );

			/* additional admin styling */
			wp_register_style( $this->admin_css_handle, $this->admin_css_url, false, false, 'all' );
			wp_enqueue_style( $this->admin_css_handle );
		}

		/**
		 * deletes saved options from database
		 */
		protected function plugin_options_delete () {
			$this->_delete_option ( $this->plugin_constant );

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
		protected function plugin_options_read () {
			$options = $this->_get_option( $this->plugin_constant );

			/* this is the point to make any migrations from previous versions */
			$this->plugin_hook_options_migrate( $options );

			/* map missing values from default */
			foreach ( $this->defaults as $key => $default )
				if ( !@array_key_exists ( $key, $options ) )
					$options[$key] = $default;

			/* removed unused keys, rare, but possible */
			foreach ( array_keys ( $options ) as $key )
				if ( !@array_key_exists( $key, $this->defaults ) )
					unset ( $options[$key] );

			/* any additional read hook */
			$this->plugin_hook_options_read( $options );

			$this->options = $options;
		}

		/**
		 * hook for parameter migration, runs right after options read from DB
		 */
		abstract function plugin_hook_options_migrate( &$options );

		/**
		 * hook to add functionality into plugin_options_read, runs after defaults check
		 */
		abstract function plugin_hook_options_read ( &$options );

		/**
		 * used on update and to save current options to database
		 *
		 * @param boolean $activating [optional] true on activation hook
		 *
		 */
		protected function plugin_options_save ( $activating = false ) {

			/* only try to update defaults if it's not activation hook, $_POST is not empty and the post
			   is ours */
			if ( !$activating && !empty ( $_POST ) && isset( $_POST[ $this->button_save ] ) ) {
				/* we'll only update those that exist in the defaults array */
				$options = $this->defaults;

				foreach ( $options as $key => $default )
				{
					/* $_POST element is available */
					if ( !empty( $_POST[$key] ) ) {
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
					elseif ( empty( $_POST[$key] ) && ( is_bool ( $default ) || is_int( $default ) ) ) {
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
			$this->_update_option (  $this->plugin_constant , $this->options );
		}

		/**
		 * hook to add functionality into plugin_options_save
		 */
		abstract function plugin_hook_options_save ( $activating );

		/**
		 * function to easily print a variable
		 *
		 * @param mixed $var Variable to dump
		 * @param boolean $ret Return text instead of printing if true
		 *
		*/
		protected function print_var ( $var , $ret = false ) {
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
		protected function print_default ( $e ) {
			_e('Default : ', $this->plugin_constant);
			$select = 'select_' . $e;
			if ( @is_array ( $this->$select ) ) {
				$x = $this->$select;
				$this->print_var ( $x[ $this->defaults[ $e ] ] );
			}
			else {
				$this->print_var ( $this->defaults[ $e ] );
			}
		}

		/**
		 * select options field processor
		 *
		 * @param elements
		 *  array to build <option> values of
		 *
		 * @param $current
		 *  the current active element
		 *
		 * @param $print
		 *  boolean: is true, the options will be printed, otherwise the string will be returned
		 *
		 * @return
		 * 	prints or returns the options string
		 *
		 */
		protected function print_select_options ( $elements, $current, $valid = false, $print = true ) {

			if ( is_array ( $valid ) )
				$check_disabled = true;
			else
				$check_disabled = false;

			$opt = '';
			foreach ($elements as $value => $name ) {
				//$disabled .= ( @array_key_exists( $valid[ $value ] ) && $valid[ $value ] == false ) ? ' disabled="disabled"' : '';
				$opt .= '<option value="' . $value . '" ';
				$opt .= selected( $value , $current );

				// ugly tree level valid check to prevent array warning messages
				if ( is_array( $valid ) && isset ( $valid [ $value ] ) && $valid [ $value ] == false )
					$opt .= ' disabled="disabled"';

				$opt .= '>';
				$opt .= $name;
				$opt .= "</option>\n";
			}

			if ( $print )
				echo $opt;
			else
				return $opt;
		}

		/**
		 * creates PayPal donation form based on plugin details & hardcoded business ID
		 *
		 */
		protected function plugin_donation_form () {

			?>
			<script>
				jQuery(document).ready(function($) {
					jQuery(function() {
						var select = $( "#amount" );
						var slider = $( '<div id="donation-slider"></div>' ).insertAfter( select ).slider({
							min: 1,
							max: 8,
							range: "min",
							value: select[ 0 ].selectedIndex + 1,
							slide: function( event, ui ) {
								select[ 0 ].selectedIndex = ui.value - 1;
							}
						});
						$( "#amount" ).change(function() {
							slider.slider( "value", this.selectedIndex + 1 );
						});
					});
				});
			</script>

			<form action="https://www.paypal.com/cgi-bin/webscr" method="post" class="<?php echo $this->plugin_constant ?>-donation">
				<label for="amount"><?php _e( "This plugin helped your business? I'd appreciate a coffee in return :) Please!", $this->plugin_constant ); ?></label>
				<select name="amount" id="amount">
					<option value="3">3$</option>
					<option value="5">5$</option>
					<option value="10" selected="selected">10$</option>
					<option value="15">15$</option>
					<option value="30">30$</option>
					<option value="42">42$</option>
					<option value="75">75$</option>
					<option value="100">100$</option>
				</select>
				<input type="hidden" id="cmd" name="cmd" value="_donations" />
				<input type="hidden" id="tax" name="tax" value="0" />
				<input type="hidden" id="business" name="business" value="<?php echo self::donation_business_id ?>" />
				<input type="hidden" id="bn" name="bn" value="<?php echo $this->donation_business_name ?>" />
				<input type="hidden" id="item_name" name="item_name" value="<?php _e('Donation for ', $this->plugin_constant ); echo $this->donation_item_name ?>" />
				<input type="hidden" id="currency_code" name="currency_code" value="USD" />
				<input type="submit" name="submit" value="<?php _e('Donate via PayPal', $this->plugin_constant ) ?>" class="button-secondary" />
			</form>
			<?php
		}

		/**
		 * log wrapper to include options
		 *
		 */
		protected function log ( $message, $log_level = LOG_WARNING ) {
			if ( !isset ( $this->options['log'] ) || $this->options['log'] != 1 )
				return false;
			else
				$this->utilities->log ( $this->plugin_constant, $message, $log_level );
		}

		/**
		 * option update; will handle network wide or standalone site options
		 *
		 */
		protected function _update_option ( $optionID, $data ) {
			if ( $this->network )
				update_site_option( $optionID , $data );
			else
				update_option( $optionID , $data );
		}

		/**
		 * read option; will handle network wide or standalone site options
		 *
		 */
		protected function _get_option ( $optionID ) {
			if ( $this->network )
				$options = get_site_option( $optionID );
			else
				$options = get_option( $optionID );

			return $options;
		}

		/**
		 * read option; will handle network wide or standalone site options
		 *
		 */
		protected function _site_url ( $site = '' ) {
			if ( $this->network && !empty( $site ) )
				$url = get_blog_option ( $site, 'siteurl' );
			else
				$url = get_bloginfo ( 'url' );

			return $url;
		}

		/**
		 * clear option; will handle network wide or standalone site options
		 *
		 */
		protected function _delete_option ( $optionID ) {
			if ( $this->network )
				delete_site_option( $optionID );
			else
				delete_option( $optionID );
		}

	}
}
