<?php
/**
 * Plugin Name: WooCommerce for Buddypress
 * Plugin URI:  https://github.com/Themekraft/WooCommerce-for-Buddypress
 * Description: Integrates a WooCommerce installation with a BuddyPress social network
 * Author:      BP Shop Dev Team
 * Version:     1.2.1
 * Author URI:  https://github.com/Themekraft/WooCommerce-for-Buddypress
 * Network:	false
 *
 *****************************************************************************
 *
 * This script is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 ****************************************************************************
 */

 // Needs to be rewritetn in Otto style ;-)
 if( ! defined( 'BP_VERSION' )){ 
	add_action( 'admin_notices', create_function( '', 'printf(\'<div id="message" class="error"><p><strong>\' . __(\'BP Shop needs BuddyPress to be installed. <a href="%s">Download it now</a>!\', "bpshop" ) . \'</strong></p></div>\', admin_url("plugin-install.php") );' ) );
	return;
}

$GLOBALS['BPSHOP_Loader_new'] = new BPSHOP_Loader();

class BPSHOP_Loader {
	/**
	 * The plugin version
	 */
	const VERSION 	= '1.1';

	/**
	 * Minimum required WP version
	 */
	const MIN_WP 	= '3.4.1';

	/**
	 * Minimum required BP version
	 */
	const MIN_BP 	= '1.6.1';

	/**
	 * Minimum required woocommerce version
	 */
	const MIN_WOO 	= '1.6.3';

	/**
	 * Name of the plugin folder
	 */
	static $plugin_name;

	/**
	 * Can the plugin be executed
	 */
	static $active = false;


	/**
	 * Self Upgrade Values
	 */
	// Base URL to the remote upgrade API server
	public $upgrade_url = 'http://themekraft.com/';

	/**
	 * @var string
	 */
	public $version = '1.6.3';

	/**
	 * @var string
	 */
	public $wc4bp_version_name = 'wc4bp_version';

	/**
	 * Initiate the class
	 *
	 * @package WooCommerce for BuddyPress
	 * @since 0.1-beta
	 */
	 
	public function __construct() {
		self::$plugin_name = plugin_basename( __FILE__ );

		// Run the activation function
		register_activation_hook( __FILE__, array( $this, 'activation' 			)		);
		
		$this->constants();
		
		add_action('plugins_loaded'					, array($this, 'translate'));
		add_action('bp_include'						, array($this, 'check_requirements'), 0);
		add_action('bp_include'						, array($this, 'includes') , 10 );
						
		if (is_admin()){
			//add_action('bp_include'					, array($this, 'load_plugin_self_updater') , 20 );
		}
		
		 /**
		 * Deletes all data if plugin deactivated
		 */
		register_deactivation_hook( __FILE__, array( $this, 'uninstall' 			)		 );
		
	}

	/**
	 * Load all BP related files
	 *
	 * Attached to bp_include. Stops the plugin if certain conditions are not met.
	 *
	 * @since 	1.0
	 * @access 	public
	 */
	public function includes() {
		if( self::$active === false )
			return false;

		// core component
		require( BPSHOP_ABSPATH .'core/bpshop-component.php' );
		
		if (is_admin()){
			// License Key API Class
			require_once( plugin_dir_path( __FILE__ ) . 'resources/api-manager/classes/class-wc4bp-key-api.php');

			// Plugin Updater Class
			require_once( plugin_dir_path( __FILE__ ) . 'resources/api-manager/classes/class-wc4bp-plugin-update.php');

			// API License Key Registration Form
			require_once( plugin_dir_path( __FILE__ ) . 'admin/admin.php');			// API License Key Registration Form
			require_once( plugin_dir_path( __FILE__ ) . 'admin/license-registration.php');
			$this->load_plugin_self_updater();
		
		}
					
	}

	/**
	 * Check for required versions
	 *
	 * Checks for WP, BP, PHP and Woocommerce versions
	 *
	 * @since 	1.0
	 * @access 	private
	 * @global 	string 	$wp_version 	Current WordPress version
	 * @return 	boolean
	 */
	public function check_requirements() {
		global $wp_version, $wpdb;

		$error = $check_wc = false;

		// only check WC on the main site on MS installations
		$check_wc = true;
		if( is_multisite() ) :
			$check_wc = false;
			if( defined( 'BLOG_ID_CURRENT_SITE' ) && $wpdb->blogid != BLOG_ID_CURRENT_SITE )
				$check_wc = true;
		endif;
		
		// BuddyPress checks
		if( ! defined( 'BP_VERSION' )){ 
			add_action( 'admin_notices', create_function( '', 'printf(\'<div id="message" class="error"><p><strong>\' . __(\'BP Shop needs BuddyPress to be installed. <a href="%s">Download it now</a>!\', "bpshop" ) . \'</strong></p></div>\', admin_url("plugin-install.php") );' ) );
			$error = true;
		}
		elseif( version_compare( BP_VERSION, self::MIN_BP, '>=' ) == false )
		{
			add_action( 'admin_notices', create_function( '', 'printf(\'<div id="message" class="error"><p><strong>\' . __(\'BP Shop works only under BuddyPress %s or higher. <a href="%s">Upgrade now</a>!\', "bpshop" ) . \'</strong></p></div>\', BPSHOP_Loader::MIN_BP, admin_url("update-core.php") );' ) );
			$error = true;
		}
		if( defined( 'BP_VERSION' )){ 
			if(function_exists('bp_is_active')){
				if(!bp_is_active('settings')){
					add_action( 'admin_notices', create_function( '', 'printf(\'<div id="message" class="error"><p><strong>\' . __(\'BP Shop works only with the BuddyPress Account Settings Component activated <a href="%s">Activate now</a>!\', "bpshop" ) . \'</strong></p></div>\', admin_url("options-general.php?page=bp-components") );' ) );
					$error = true;	
				}
			}
		}
		// Woocommerce checks
		if( $check_wc ) :
			if( ! defined( 'WOOCOMMERCE_VERSION' ) ) {
				add_action( 'admin_notices', create_function( '', 'printf(\'<div id="message" class="error"><p><strong>\' . __(\'BP Shop needs WooCommerce to be installed. <a href="%s">Download it now</a>!\', "bpshop" ) . \'</strong></p></div>\', admin_url("plugin-install.php") );' ) );
				$error = true;
			}
			elseif( version_compare( WOOCOMMERCE_VERSION, self::MIN_WOO, '>=' ) == false ) {
				add_action( 'admin_notices', create_function( '', 'printf(\'<div id="message" class="error"><p><strong>\' . __(\'BP Shop works only under WooCommerce %s or higher. <a href="%s">Upgrade now</a>!\', "bpshop" ) . \'</strong></p></div>\', BPSHOP_Loader::MIN_WOO, admin_url("update-core.php") );' ) );
				$error = true;
			}
		endif;

		// WordPress check
		if( version_compare( $wp_version, self::MIN_WP, '>=' ) == false ) {
			add_action( 'admin_notices', create_function( '', 'printf(\'<div id="message" class="error"><p><strong>\' . __(\'BP Shop works only under WordPress %s or higher. <a href="%s">Upgrade now</a>!\', "bpshop" ) . \'</strong></p></div>\', BPSHOP_Loader::MIN_WP, admin_url("update-core.php") );' ) );
			$error = true;
		}

		self::$active = ( ! $error ) ? true : false;
	}

	/**
	 * Load the language file
	 *
	 * @since 	1.0
	 * @uses 	load_plugin_textdomain()
	 */
	public function translate()	{
		load_plugin_textdomain( 'bpshop', false, dirname( plugin_basename( __FILE__ ) ) . "/languages" );
	}

	/**
	 * Declare all constants
	 *
	 * @since 	1.0
	 * @access 	private
	 */
	private function constants() {
		define( 'BPSHOP_PLUGIN', 	self::$plugin_name );
		define( 'BPSHOP_VERSION',	self::VERSION );
		define( 'BPSHOP_FOLDER',	plugin_basename( dirname( __FILE__ ) ) );
		define( 'BPSHOP_ABSPATH',	trailingslashit( str_replace( "\\", "/", WP_PLUGIN_DIR .'/'. BPSHOP_FOLDER ) ) );
		define( 'BPSHOP_URLPATH',	trailingslashit( plugins_url( '/'. BPSHOP_FOLDER ) ) );
		define('BPSHOP_ABSPATH_TEMPLATE_PATH', BPSHOP_ABSPATH . 'templates/');
	}
	/**
	 * Check for software updates
	 */
	public function load_plugin_self_updater() {
		$options = get_option( 'wc4bp_license_manager' );

		// upgrade url must also be chaned in classes/class-bf-key-api.php
		$upgrade_url = 'http://themekraft.com/'; // URL to access the Update API Manager.
		$plugin_name = untrailingslashit( plugin_basename( __FILE__ ) ); // same as plugin slug. if a theme use a theme name like 'twentyeleven'
		$product_id = get_option( 'wc4bp_product_id' ); // Software Title
		$api_key = $options['api_key']; // API License Key
		$activation_email = $options['activation_email']; // License Email
		$renew_license_url = 'http://themekraft.com/my-account/'; // URL to renew a license
		$instance = get_option( 'wc4bp_instance' ); // Instance ID (unique to each blog activation)
		$domain = site_url(); // blog domain name
		$software_version = get_option( $this->wc4bp_version_name ); // The software version
		$plugin_or_theme = 'plugin'; // 'theme' or 'plugin'

		new wc4bp_Plugin_Update_API_Check( $upgrade_url, $plugin_name, $product_id, $api_key, $activation_email, $renew_license_url, $instance, $domain, $software_version, $plugin_or_theme );
	}


	/**
	 * Generate the default data arrays
	 */
	public function activation() {

		$global_options = array(
			'api_key' 			=> '',
			'activation_email' 	=> '',
		);

		update_option( 'wc4bp_license_manager', $global_options );

		// Password Management Class
		require_once( plugin_dir_path( __FILE__ ) . 'resources/api-manager/classes/class-wc4bp-passwords.php');

		$wc4bp_password_management = new wc4bp_Password_Management();

		// Generate a unique installation $instance id
		$instance = $wc4bp_password_management->generate_password( 12, false );

		$single_options = array(
			'wc4bp_product_id' 				=> 'woocommerce-buddypress-integration',
			'wc4bp_instance' 				=> $instance,
			'wc4bp_deactivate_checkbox' 	=> 'on',
			'wc4bp_activated' 				=> 'Deactivated',
			);

		foreach ( $single_options as $key => $value ) {
			update_option( $key, $value );
		}

		$curr_ver = get_option( $this->wc4bp_version_name );

		// checks if the current plugin version is lower than the version being installed
		if ( version_compare( $this->version, $curr_ver, '>' ) ) {
			// update the version
			update_option( $this->wc4bp_version_name, $this->version );
		}
		include_once( dirname( __FILE__ ) .'/admin/bpshop-activate.php' );
		bpshop_activate();
	}

	/**
	 * Deletes all data if plugin deactivated
	 * @return void
	 */
	public function uninstall() {
		global $wpdb, $blog_id;

		$this->license_key_deactivation();

		// Remove options
		if ( is_multisite() ) {

			switch_to_blog( $blog_id );

			foreach ( array(
					'wc4bp_license_manager',
					'wc4bp_product_id',
					'wc4bp_instance',
					'wc4bp_deactivate_checkbox',
					'wc4bp_activated',
					'wc4bp_version'
					) as $option) {

					delete_option( $option );

					}

			restore_current_blog();

		} else {

			foreach ( array(
					'wc4bp_license_manager',
					'wc4bp_product_id',
					'wc4bp_instance',
					'wc4bp_deactivate_checkbox',
					'wc4bp_activated'
					) as $option) {

					delete_option( $option );

					}

		}
		include_once( dirname( __FILE__ ) .'/admin/bpshop-activate.php' );
		bpshop_cleanup();
	}

	/**
	 * Deactivates the license on the API server
	 * @return void
	 */
	public function license_key_deactivation() {

		$wc4bp_key = new wc4bp_Key();

		$activation_status = get_option( 'wc4bp_activated' );

		$default_options = get_option( 'wc4bp_license_manager' );

		$api_email = $default_options['activation_email'];
		$api_key = $default_options['api_key'];

		$args = array(
			'email' => $api_email,
			'licence_key' => $api_key,
			);

		if ( $activation_status == 'Activated' && $api_key != '' && $api_email != '' ) {
			$wc4bp_key->deactivate( $args ); // reset license key activation
		}
	}

}

/**
 * The functions below do not have any filters, but can be redefined
 * Needs to happen here, so as not to cause any errors
 * Changing these functions ensures that the correct JS is being loaded
 *
 * @todo	Write a fix to use filters rather than redeclaring these functions
 * 			which could potentially create conflicts with other plugins
 */

if( ! function_exists( 'is_checkout' ) ) :
/**
 * Check if we're on a checkout page
 *
 * @since 	1.0.5
 */
function is_checkout() {
	if( is_user_logged_in() ) :
		if( bp_is_current_component( 'shop' ) && bp_is_action_variable( 'checkout' ) ) :
			return true;
		endif;
	else :
		if( is_page( woocommerce_get_page_id( 'checkout' ) ) || is_page( woocommerce_get_page_id( 'pay' ) ) ) :
			return true;
		endif;
	endif;

	return false;
}
endif;

if( ! function_exists( 'is_cart' ) ) :
/**
 * Check if we're on a cart page
 *
 * @since 	1.0.5
 */
function is_cart() {
	if( is_user_logged_in() ) :
		if( bp_is_current_component( 'shop' ) && ! bp_action_variables() ) :
			return true;
		endif;
	else :
		if( is_page( woocommerce_get_page_id( 'cart' ) ) ) :
			return true;
		endif;
	endif;

	return false;
}
endif;

if( ! function_exists( 'is_account_page' ) ) :
/**
 * Check if we're on an account page
 *
 * @since 	1.0.5
 */
function is_account_page() {
	if( is_user_logged_in() ) :
		if( bp_is_current_component( 'shop' ) && bp_is_action_variable( 'history' ) ) :
			return true;
		endif;
	else :
		if( is_page( woocommerce_get_page_id( 'myaccount' ) ) || is_page( woocommerce_get_page_id( 'edit_address' ) ) || is_page( woocommerce_get_page_id( 'view_order' ) ) || is_page( woocommerce_get_page_id( 'change_password' ) ) ) :
			return true;
		endif;
	endif;

	return false;
}
endif;