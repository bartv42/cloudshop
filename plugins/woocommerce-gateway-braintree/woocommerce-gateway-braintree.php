<?php
/**
 * Plugin Name: WooCommerce Braintree Gateway
 * Plugin URI: http://www.woothemes.com/products/braintree/
 * Description: Adds the Braintree Payment Gateway to your WooCommerce store, allowing customers to securely save their credit card to their account for use with single purchases, pre-orders, subscriptions, and more!
 * Author: SkyVerge
 * Author URI: http://www.skyverge.com
 * Version: 2.1.2
 * Text Domain: woocommerce-gateway-braintree
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2012-2014 SkyVerge, Inc. (info@skyverge.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Gateway-Braintree
 * @author    SkyVerge
 * @category  Gateway
 * @copyright Copyright (c) 2012-2014, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Required functions
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once( 'woo-includes/woo-functions.php' );
}

// Plugin updates
woothemes_queue_update( plugin_basename( __FILE__ ), '880788043990c91aa0b164aa971f78a3', '18659' );

// WC active check
if ( ! is_woocommerce_active() ) {
	return;
}

// Required library class
if ( ! class_exists( 'SV_WC_Framework_Bootstrap' ) ) {
	require_once( 'lib/skyverge/woocommerce/class-sv-wc-framework-bootstrap.php' );
}

SV_WC_Framework_Bootstrap::instance()->register_plugin( '2.0.2', __( 'WooCommerce Braintree Gateway', 'woocommerce-gateway-braintree' ), __FILE__, 'init_woocommerce_gateway_braintree' );

function init_woocommerce_gateway_braintree() {

/**
 * # WooCommerce Gateway Braintree Main Plugin Class
 *
 * ## Plugin Overview
 *
 * This plugin adds Braintree as a payment gateway. Braintree's javascript library is used to encrypt the credit card
 * fields prior to form submission, so it acts like a direct gateway but without the burden of heavy PCI compliance. Logged
 * in customers' credit cards are saved to the braintree vault by default. Subscriptions and Pre-Orders are supported via
 * the Add-Ons class.
 *
 * ## Admin Considerations
 *
 * A user view/edit field is added for the Braintree customer ID so it can easily be changed by the admin.
 *
 * ## Frontend Considerations
 *
 * Both the payment fields on checkout (and checkout->pay) and the My cards section on the My Account page are template
 * files for easy customization.
 *
 * ## Database
 *
 * ### Global Settings
 *
 * + `woocommerce_braintree_settings` - the serialized braintree settings array
 *
 * ### Options table
 *
 * + `wc_braintree_version` - the current plugin version, set on install/upgrade
 *
 * ### Order Meta
 *
 * + `_wc_braintree_trans_id` - the braintree transaction ID
 * + `_wc_braintree_trans_mode` - the environment the braintree transaction was created in
 * + `_wc_braintree_card_type` - the card type used for the order
 * + `_wc_braintree_card_last_four` - the last four digits of the card used for the order
 * + `_wc_braintree_card_exp_date` - the expiration date of the card used for the order
 * + `_wc_braintree_customer_id` - the braintree customer ID for the order, set only if the customer is logged in/creating an account
 * + `_wc_braintree_cc_token` - the braintree token for the credit card used for the order, set only if the customer is logged in/creating an account
 *
 * ### User Meta
 * + `_wc_braintree_customer_id` - the braintree customer ID for the user
 *
 */
class WC_Braintree extends SV_WC_Plugin {


	/** plugin version number */
	const VERSION = '2.1.2';

	/** plugin id */
	const PLUGIN_ID = 'braintree';

	/** plugin text domain */
	const TEXT_DOMAIN = 'woocommerce-gateway-braintree';

	/** @var string class to load as gateway, can be base or add-ons class */
	public $gateway_class_name = 'WC_Gateway_Braintree';


	/**
	 * Initializes the plugin
	 *
	 * @since 2.0
	 */
	public function __construct() {

		parent::__construct(
			self::PLUGIN_ID,
			self::VERSION,
			self::TEXT_DOMAIN,
			array( 'dependencies' => array( 'curl', 'dom', 'hash', 'openssl', 'SimpleXML', 'xmlwriter' ) )
		);

		// include required files
		add_action( 'sv_wc_framework_plugins_loaded', array( $this, 'includes' ) );

		// add the 'My Cards' on the 'My Account' page
		add_action( 'woocommerce_after_my_account', array( $this, 'render_my_cards' ) );

		// load templates, called just before the woocommerce template functions are included
		add_action( 'init', array( $this, 'include_template_functions' ), 25 );

		// admin
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {

			// show braintree customer ID field on edit user pages
			add_action( 'show_user_profile', array( $this, 'render_braintree_customer_id_meta_field' ) );
			add_action( 'edit_user_profile', array( $this, 'render_braintree_customer_id_meta_field' ) );

			// save braintree customer ID field
			add_action( 'personal_options_update',  array( $this, 'save_braintree_customer_id_meta_field' ) );
			add_action( 'edit_user_profile_update', array( $this, 'save_braintree_customer_id_meta_field' ) );
		}
	}


	/**
	 * Include required files
	 *
	 * @since 2.0
	 */
	public function includes() {

		// base gateway class
		require_once( 'includes/class-wc-gateway-braintree.php' );

		// exception class
		require_once( 'includes/class-wc-gateway-braintree-exception.php' );

		// load add-ons class if subscriptions and/or pre-orders are active
		if ( $this->is_subscriptions_active() || $this->is_pre_orders_active() ) {

			require_once( 'includes/class-wc-gateway-braintree-addons.php' );

			$this->gateway_class_name = 'WC_Gateway_Braintree_Addons';
		}

		// add to WC payment methods
		add_filter( 'woocommerce_payment_gateways', array( $this, 'load_gateway' ) );
	}


	/**
	 * Adds Braintree the list of available payment gateways
	 *
	 * @since 1.0
	 * @param array $gateways
	 * @return array $gateways
	 */
	public function load_gateway( $gateways ) {

		$gateways[] = $this->gateway_class_name;

		return $gateways;
	}


	/**
	 * Handle localization, WPML compatible
	 *
	 * @since 2.0
	 * @see SV_WC_Plugin::load_translation()
	 */
	public function load_translation() {

		load_plugin_textdomain( 'woocommerce-gateway-braintree', false, dirname( plugin_basename( $this->get_file() ) ) . '/i18n/languages' );
	}


	/**
	 * Function used to init template functions,
	 * making them pluggable by plugins and themes.
	 *
	 * @since 2.0
	 */
	public function include_template_functions() {
		include_once( 'includes/wc-gateway-braintree-template.php' );
	}


	/** Frontend methods ******************************************************/


	/**
	 * Helper to add the 'My Cards' section to the 'My Account' page
	 *
	 * @since 1.0
	 */
	public function render_my_cards() {

		woocommerce_braintree_my_cards( new $this->gateway_class_name );
	}


	/** Admin methods ******************************************************/

	/**
	 * Render a notice for the user to select their desired export format
	 *
	 * @since 2.1
	 * @see SV_WC_Plugin::render_admin_notices()
	 */
	public function render_admin_notices() {

		// show any dependency notices
		parent::render_admin_notices();

		$settings = get_option( 'woocommerce_braintree_settings' );

		// install notice
		if ( empty( $settings) && ! $this->is_message_dismissed( 'install-notice' ) ) {

			$this->add_dismissible_notice(
				sprintf( __( 'Thanks for installing the WooCommerce Braintree plugin! To start accepting payments, %sset your Braintree API credentials%s. Need help? See the %sdocumentation%s. ', self::TEXT_DOMAIN ),
					'<a href="' . $this->get_settings_url() . '">', '</a>',
					'<a target="_blank" href="' . $this->get_documentation_url() . '">', '</a>'
				), 'install-notice'
			);
		}

		// SSL check (only when enabled in production mode)
		if ( isset( $settings['enabled'] ) && 'yes' == $settings['enabled'] ) {
			if ( isset( $settings['environment'] ) && 'production' == $settings['environment'] ) {

				if ( 'no' === get_option( 'woocommerce_force_ssl_checkout' ) && ! $this->is_message_dismissed( 'ssl-recommended-notice' ) ) {

					$this->add_dismissible_notice( __( 'WooCommerce is not being forced over SSL -- Braintree recommends forcing the checkout over SSL for maximum security. ', self::TEXT_DOMAIN ), 'ssl-recommended-notice' );
				}
			}
		}
	}


	/**
	 * Display a field for the Braintree customer ID meta on the view/edit user page
	 *
	 * @since 2.0
	 * @param WP_User $user user object for the current edit page
	 */
	public function render_braintree_customer_id_meta_field( $user ) {

		// bail if the current user is not allowed to manage woocommerce
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		?>
		<h3><?php _e( 'Braintree Customer Details', self::TEXT_DOMAIN ) ?></h3>
		<table class="form-table">
			<tr>
				<th><label for="_wc_braintree_customer_id"><?php _e( 'Customer ID', self::TEXT_DOMAIN ); ?></label></th>
				<td>
					<input type="text" name="_wc_braintree_customer_id" id="_wc_braintree_customer_id" value="<?php echo esc_attr( get_user_meta( $user->ID, '_wc_braintree_customer_id', true ) ); ?>" class="regular-text" /><br/>
					<span class="description"><?php _e( 'The Braintree customer ID for the user. Only edit this if necessary.', self::TEXT_DOMAIN ); ?></span>
				</td>
			</tr>
		</table>
	<?php
	}


	/**
	 * Save the Braintree customer ID meta field on the view/edit user page
	 *
	 * @since 2.0
	 * @param int $user_id identifies the user to save the settings for
	 */
	public function save_braintree_customer_id_meta_field( $user_id ) {

		// bail if the current user is not allowed to manage woocommerce
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! empty( $_POST['_wc_braintree_customer_id'] ) ) {
			update_user_meta( $user_id, '_wc_braintree_customer_id', trim( $_POST['_wc_braintree_customer_id'] ) );
		} else {
			delete_user_meta( $user_id, '_wc_braintree_customer_id' );
		}
	}


	/** Helper methods ******************************************************/


	/**
	 * Checks is WooCommerce Subscriptions is active
	 *
	 * @since 2.0
	 * @return bool true if WCS is active, false if not active
	 */
	public function is_subscriptions_active() {

		return $this->is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' );
	}


	/**
	 * Checks is WooCommerce Pre-Orders is active
	 *
	 * @since 2.0
	 * @return bool true if WC Pre-Orders is active, false if not active
	 */
	public function is_pre_orders_active() {

		return $this->is_plugin_active( 'woocommerce-pre-orders/woocommerce-pre-orders.php' );
	}


	/**
	 * Returns the plugin name, localized
	 *
	 * @since 2.1
	 * @see SV_WC_Plugin::get_plugin_name()
	 * @return string the plugin name
	 */
	public function get_plugin_name() {
		return __( 'WooCommerce Braintree Gateway', self::TEXT_DOMAIN );
	}


	/**
	 * Returns __FILE__
	 *
	 * @since 2.1
	 * @see SV_WC_Plugin::get_file()
	 * @return string the full path and filename of the plugin file
	 */
	protected function get_file() {
		return __FILE__;
	}


	/**
	 * Gets the plugin documentation url
	 *
	 * @since 2.1
	 * @see SV_WC_Plugin::get_documentation_url()
	 * @return string documentation URL
	 */
	public function get_documentation_url() {
		return 'http://docs.woothemes.com/document/braintree/';
	}


	/**
	 * Gets the gateway configuration URL
	 *
	 * @since 2.1
	 * @see SV_WC_Plugin::get_settings_url()
	 * @param string $_ unused
	 * @return string plugin settings URL
	 */
	public function get_settings_url( $_ = null ) {

		return SV_WC_Plugin_Compatibility::get_payment_gateway_configuration_url( $this->gateway_class_name );
	}


	/**
	 * Returns true if on the gateway settings page
	 *
	 * @since 2.1
	 * @see SV_WC_Plugin::is_plugin_settings()
	 * @return boolean true if on the admin gateway settings page
	 */
	public function is_plugin_settings() {

		return SV_WC_Plugin_Compatibility::is_payment_gateway_configuration_page( $this->gateway_class_name );
	}


	/** Lifecycle methods ******************************************************/


	/**
	 * Perform any version-related changes.
	 *
	 * @since 2.0
	 * @param int $installed_version the currently installed version of the plugin
	 */
	protected function upgrade( $installed_version ) {

		// pre-2.0 upgrade
		if ( version_compare( $installed_version, '2.0', '<' ) ) {
			global $wpdb;

			// update from pre-2.0 Braintree version
			if ( $settings = get_option( 'woocommerce_braintree_settings' ) ) {

				// migrate from old settings
				$settings['cvv_required'] = $settings['cvvrequired'];
				$settings['merchant_id']  = $settings['merchantid'];
				$settings['public_key']   = $settings['publickey'];
				$settings['private_key']  = $settings['privatekey'];
				$settings['debug_mode']   = 'off';

				// remove unused settings
				foreach ( array( 'cvvrequired', 'vault', 'vaulttext', 'managecards', 'merchantid', 'publickey', 'privatekey' ) as $key ) {

					if ( isset( $settings[ $key ] ) )
						unset( $settings[ $key ] );
				}

				// update to new settings
				update_option( 'woocommerce_braintree_settings', $settings );

				// update user meta keys
				$wpdb->update( $wpdb->usermeta, array( 'meta_key' => '_wc_braintree_customer_id' ), array( 'meta_key' => 'woocommerce_braintree_customerid' ) );

				// update post meta keys
				$wpdb->update( $wpdb->postmeta, array( 'meta_key' => '_wc_braintree_cc_token' ), array( 'meta_key' => '_braintree_token' ) );

				// remove unused tokens
				$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'woocommerce_braintree_cc' ) );
			}

			// update from Braintree TR extension
			if ( $settings = get_option( 'woocommerce_braintree_tr_settings' ) ) {

				/* migrate from old settings */

				// debug mode
				if ( 'yes' == $settings['debug'] && 'yes' == $settings['log'] )
					$settings['debug_mode'] = 'both';
				elseif ( 'yes' == $settings['debug'] )
					$settings['debug_mode'] = 'checkout';
				elseif ( 'yes' == $settings['log'] )
					$settings['debug_mode'] = 'log';
				else
					$settings['debug_mode'] = 'off';

				// other settings
				$settings['card_types']  = $settings['cardtypes'];
				$settings['merchant_id'] = $settings['merchantid'];
				$settings['public_key']  = $settings['publickey'];
				$settings['private_key'] = $settings['privatekey'];

				// remove unused settings
				foreach ( array( 'debug', 'log', 'custom_order_numbers', 'vault', 'vaulttext', 'managecards', 'cardtypes', 'merchantid', 'publickey', 'privatekey' ) as $key ) {

					if ( isset( $settings[ $key ] ) )
						unset( $settings[ $key ] );
				}

				// update to new settings
				update_option( 'woocommerce_braintree_settings', $settings );

				// update user meta keys
				$wpdb->update( $wpdb->usermeta, array( 'meta_key' => '_wc_braintree_customer_id' ), array( 'meta_key' => 'woocommerce_braintree_customerid' ) );

				// update post meta keys
				$wpdb->update( $wpdb->postmeta, array( 'meta_key' => '_wc_braintree_customer_id' ),    array( 'meta_key' => '_braintree_customerid' ) );
				$wpdb->update( $wpdb->postmeta, array( 'meta_key' => '_wc_braintree_cc_token' ),       array( 'meta_key' => '_braintree_token' ) );
				$wpdb->update( $wpdb->postmeta, array( 'meta_key' => '_wc_braintree_trans_env' ),      array( 'meta_key' => '_braintree_transaction_environment' ) );
				$wpdb->update( $wpdb->postmeta, array( 'meta_key' => '_wc_braintree_card_exp_date' ),  array( 'meta_key' => '_braintree_cc_expiration' ) );
				$wpdb->update( $wpdb->postmeta, array( 'meta_key' => '_wc_braintree_card_type' ),      array( 'meta_key' => '_braintree_cc_card_type' ) );
				$wpdb->update( $wpdb->postmeta, array( 'meta_key' => '_wc_braintree_card_last_four' ), array( 'meta_key' => '_braintree_cc_last4' ) );
				$wpdb->update( $wpdb->postmeta, array( 'meta_key' => '_wc_braintree_trans_id' ),       array( 'meta_key' => '_braintree_transaction_id' ) );

				// remove unused tokens
				$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'woocommerce_braintree_cc' ) );

				// disable plugin by removing settings
				delete_option( 'woocommerce_braintree_tr_settings' );
			}

		}
	}


} // end \WC_Braintree class



/**
 * The WC_Braintree global object
 * @name $wc_braintree
 * @global WC_Braintree $GLOBALS['wc_braintree']
 */
$GLOBALS['wc_braintree'] = new WC_Braintree();

} // init_woocommerce_gateway_braintree()
