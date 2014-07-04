<?php
/**
 * Plugin Name: Custom order numbers for Blender.org
 * Description: Based on Sequential Numbers Pro, Modified by Bart Veldhuizen
 * Author: SkyVerge
 * Author URI: http://www.skyverge.com
 * Version: 1.6.1
 * Text Domain: woocommerce-sequential-order-numbers-pro
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2012-2014 SkyVerge, Inc. (info@skyverge.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Sequential-Order-Numbers-Pro
 * @author    SkyVerge
 * @category  Plugin
 * @copyright Copyright (c) 2013-2014, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Required functions
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once( 'woo-includes/woo-functions.php' );
}

// Plugin updates
woothemes_queue_update( plugin_basename( __FILE__ ), '0b18a2816e016ba9988b93b1cd8fe766', '18688' );

// WC active check
if ( ! is_woocommerce_active() ) {
	return;
}

// Required library classss
if ( ! class_exists( 'SV_WC_Framework_Bootstrap' ) ) {
	require_once( 'lib/skyverge/woocommerce/class-sv-wc-framework-bootstrap.php' );
}

SV_WC_Framework_Bootstrap::instance()->register_plugin( '2.0.2', __( 'WooCommerce Sequential Order Numbers Pro', 'woocommerce-sequential-order-numbers-pro' ), __FILE__, 'init_woocommerce_sequential_order_numbers_pro' );

function init_woocommerce_sequential_order_numbers_pro() {

/**
 * # WooCommerce Sequential Order Numbers Main Plugin Class
 *
 * ## Plugin Overview
 *
 * By default WooCommerce orders take their order number from the underlying WP
 * post ID.  This plugin allows for the creation and customization of a
 * sequential order number which is displayed and usable throughout the admin
 * and frontend thanks to a variety of filters and action hooks, and is
 * compatible with all other properly written WooCommerce plugins and gateways
 * that make use of the generic WC_Order::get_order_number() method.
 *
 * ## Admin Considerations
 *
 * This plugin adds an "Order Numbers" configuration section to the WooCommerce
 * &gt; General settings tab.  The custom order number is displayed and made
 * searchable throughout the admin.
 *
 * ## Frontend Considerations
 *
 * This plugin displays the custom order number throughout the frontend,
 * including the order confirmation pages and emails, and makes the custom
 * order number searchable by the customer on the Order Tracking page.
 *
 * ## Database
 *
 * This plugin functions by creating two important custom order meta fields:
 * _order_number which contains the incrementing numerical portion of
 * the custom order number, and _order_number_formatted, which contains
 * the full alpha-numeric order number, with an optional prefix and
 * postfix prepended and appended, respectively.
 *
 * Newly placed orders will be assigned an order number equal to the
 * configurable order number start option, or the max existing order number + 1,
 * whichever is greater.
 *
 * ## Options table
 *
 * `wc_sequential_order_numbers_pro_version` - the current plugin version,
 * set on install/upgrade
 *
 * `woocommerce_order_number_start` - The starting number for the incrementing
 * portion of the order numbers, unless there is an existing order with a higher
 * number.
 *
 * `woocommerce_order_number_length` - the desired minimum length of the
 * incrementing portion of the order number
 *
 * **woocommerce_order_number_prefix` - user-supplied order number prefix
 *
 * `woocommerce_order_number_suffix` - user-supplied order number suffix
 *
 * `woocommerce_hash_before_order_number` - indicates whether to remove the
 * hash (#) that is typically displayed before order numbers.  The hash is purely
 * cosmetic.
 *
 * `woocommerce_order_number_skip_free_orders` - whether to assign free
 * orders a separate sequence
 *
 * `woocommerce_free_order_number_start` - The starting number for the
 * incrementing portion of the order numbers for FREE orders, unless there
 * is an existing FREE order with a higher number.  (this is only applicable
 * when "Skip Free Orders" is enabled)
 *
 * `woocommerce_free_order_number_prefix` - user-supplied free order number
 * prefix (this is only applicable when "Skip Free Orders" is enabled)
 *
 * `wc_sequential_order_numbers_pro_install_offset` - used on upgrade from the
 * free plugin to keep track of how far we made it in case we hit a script timeout
 *
 * ### Order Postmeta
 *
 * `_order_number` - int The numeric sequential portion of the order number
 *
 * `_order_number_free` - int The numeric sequential portion of the order
 * number for free orders, when the "Skip Free Orders" option is enabled.  When
 * this is used _order_number will be '-1'
 *
 * `_order_number_formatted` - string The formatted order number.  This is,
 * or contains, _order_number or _order_number_free
 *
 * `_order_number_meta` - array The settings at the time of the order number
 * generation with the following keys: 'prefix', 'suffix' and 'length'
 */
class WC_Seq_Order_Number_Pro extends SV_WC_Plugin {


	/** version number */
	const VERSION = '1.6.1';

	/** The plugins id, used for various slugs and such */
	const PLUGIN_ID = 'sequential_order_numbers_pro';

	/** Plugin text domain */
	const TEXT_DOMAIN = 'woocommerce-sequential-order-numbers-pro';


	/**
	 * @var string
	 */
	private $errors = null;

	/**
	 * @var string
	 */
	private $order_number_prefix;

	/**
	 * @var string
	 */
	private $order_number_suffix;

	/**
	 * @var int
	 */
	private $order_number_length;

	/** @var int maximum order number */
	private $max_order_number;


	/**
	 * Plugin constructor
	 *
	 * @see SV_WC_Plugin::__construct()
	 */
	public function __construct() {

		parent::__construct(
			self::PLUGIN_ID,
			self::VERSION,
			self::TEXT_DOMAIN
		);

		// set the custom order number on the new order.  we hook into woocommerce_checkout_update_order_meta for orders which are created
		//  from the frontend, and we hook into woocommerce_process_shop_order_meta for admin-created orders.
		//  Note we use these actions rather than the more generic wp_insert_post action because we want to
		//  run after the order meta (including totals) are set so we can detect whether this is a free order
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'set_sequential_order_number' ), 10, 2 );
		add_action( 'woocommerce_process_shop_order_meta',    array( $this, 'set_sequential_order_number' ), 15, 2 );
		add_action( 'woocommerce_before_resend_order_emails', array( $this, 'set_sequential_order_number' ), 10, 1 );

		// return our custom order number for display
		add_filter( 'woocommerce_order_number', array( $this, 'get_order_number' ), 10, 2);

		// order tracking page search by order number
		add_filter( 'woocommerce_shortcode_order_tracking_order_id', array( $this, 'find_order_by_order_number' ) );

		// WC Subscriptions support: prevent unnecessary order meta from polluting parent renewal orders, and set order number for subscription orders
		add_filter( 'woocommerce_subscriptions_renewal_order_meta_query', array( $this, 'subscriptions_remove_renewal_order_meta' ), 10, 4 );
		add_action( 'woocommerce_subscriptions_renewal_order_created',    array( $this, 'subscriptions_set_sequential_order_number' ), 9, 4 );

		if ( is_admin() ) {
			// keep the admin order search/order working properly
			add_filter( 'request',                              array( $this, 'woocommerce_custom_shop_order_orderby' ), 20 );
			add_filter( 'woocommerce_shop_order_search_fields', array( $this, 'custom_search_fields' ) );

			// sort by underlying _order_number on the Pre-Orders table
			add_filter( 'wc_pre_orders_edit_pre_orders_request', array( $this, 'custom_orderby' ) );
			add_filter( 'wc_pre_orders_search_fields',           array( $this, 'custom_search_fields' ) );

			// inject our admin options
			add_filter( 'woocommerce_general_settings', array( $this, 'admin_settings' ) );
			add_action( 'woocommerce_settings_start',   array( $this, 'admin_settings_js' ) );

			// roll my own Settings field error check, which isn't beautiful, but is important
			add_filter( 'pre_update_option_woocommerce_order_number_start', array( $this, 'validate_order_number_start_setting' ), 10, 2 );

			// pre WC 2.1
			add_filter( 'wp_redirect',                                      array( $this, 'add_settings_error_msg' ), 10, 2 );

			// add support for the CSV export plugin
			add_filter( 'woocommerce_export_csv_extra_columns', array( $this, 'export_csv_extra_columns' ) );

			add_action( 'woocommerce_settings_start', array( $this, 'add_settings_errors' ) );

		}
	}


	/**
	 * Load plugin text domain.
	 *
	 * @see SV_WC_Plugin::load_translation()
	 */
	public function load_translation() {
		load_plugin_textdomain( 'woocommerce-sequential-order-numbers-pro', false, dirname( plugin_basename( $this->get_file() ) ) . '/i18n/languages' );
	}


	/**
	 * Search for an order with order_number $order_number.  This method can be
	 * useful for 3rd party plugins that want to rely on the Sequential Order
	 * Numbers plugin and perform lookups by custom order number.
	 *
	 * @param string $order_number order number to search for
	 * @return int post_id for the order identified by $order_number, or 0
	 */
	public function find_order_by_order_number( $order_number ) {

		// search for the order by custom order number
		$query_args = array(
			'numberposts' => 1,
			'meta_key'    => '_order_number_formatted',
			'meta_value'  => $order_number,
			'post_type'   => 'shop_order',
			'post_status' => 'publish',
			'fields'      => 'ids',
		);

		list( $order_id ) = get_posts( $query_args );

		// order was found
		if ( null !== $order_id ) {
			return $order_id;
		}

		// if we didn't find the order, then it may be that this plugin was disabled and an order was placed in the interim
		$order = new WC_Order( $order_number );
		if ( '' !== SV_WC_Plugin_Compatibility::get_order_custom_field( $order, 'order_number_formatted' ) ) {
			// _order_number was set, so this is not an old order, it's a new one that just happened to have post_id that matched the searched-for order_number
			return 0;
		}

		return $order->id;
	}


	/**
	 * Set the _order_number/_order_number_formatted field for the newly created order
	 *
	 * @param int|WC_Order $post_id order identifier or order object
	 * @param mixed $post this is going to be an array of the POST values when
	 *        the order is created from the checkout page in the frontend, null
	 *        when the checkout method is PayPal Expres, and a post object when
	 *        the order is created in the admin.  Defaults to an array so that
	 *        other actions can be hooked in
	 */
	public function set_sequential_order_number( $post_id, $post = array() ) {

		// when creating an order from the admin don't create order numbers for auto-draft
		//  orders, because these are not linked to from the admin and so difficult to delete
		if ( is_array( $post ) || is_null( $post ) || ( 'shop_order' == $post->post_type && 'auto-draft' != $post->post_status ) ) {

			$post_id = is_a( $post_id, 'WC_Order' ) ? $post_id->id : $post_id;
			$order_number = get_post_meta( $post_id, '_order_number' );

			// if no order number has been assigned, this will be an empty array
			if ( empty( $order_number ) ) {

				if ( $this->skip_free_orders() && $this->is_free_order( $post_id ) ) {
					// assign sequential free order number
					if ( $this->generate_sequential_order_number( $post_id, '_order_number_free', $this->get_free_order_number_start(), $this->get_free_order_number_prefix() ) ) {
						// so that sorting still works in the admin
						update_post_meta( $post_id, '_order_number', -1 );
					}
				} else {
					// normal operation
					$this->generate_sequential_order_number( $post_id, '_order_number', get_option( 'woocommerce_order_number_start' ), $this->get_order_number_prefix(), $this->get_order_number_suffix(), $this->get_order_number_length() );
				}
			}
		}
	}


	/**
	 * Safely generate and assign a sequential order number
	 *
	 * @since 1.3
	 *
	 * @param int $post_id order identifier
	 * @param string $order_number_meta_name order number meta name,
	 *        ie _order_number or _order_number_free
	 * @param int $order_number_start order number starting point
	 * @param string $order_number_prefix optional order number prefix
	 * @param string $order_number_suffix optional order number suffix
	 * @param int $order_number_length optional order number length
	 *
	 * @return boolean true if a sequential order number was successfully
	 *         generated and assigned to $post_id
	 */
	private function generate_sequential_order_number( $post_id, $order_number_meta_name, $order_number_start, $order_number_prefix = '', $order_number_suffix = '', $order_number_length = 1 ) {
		global $wpdb;

		$success = false;
		
		$today = date ( 'ymd' );

		for ( $i = 0; $i < 3 && ! $success; $i++ ) {
			// add $order_number_meta_name equal to $order_number_start if there are no existing orders with an $order_number_meta_name meta
			//  or $order_number_start is larger than the max existing $order_number_meta_name meta.  Otherwise, $order_number_meta_name
			//  will be set to the max $order_number_meta_name + 1
			$query = $wpdb->prepare( "
				INSERT INTO {$wpdb->postmeta} (post_id,meta_key,meta_value)
				SELECT %d,'{$order_number_meta_name}',IF(MAX(CAST(pm1.meta_value AS SIGNED)) IS NULL OR MAX(CAST(pm1.meta_value AS SIGNED)) < 1, 1, MAX(CAST(pm1.meta_value AS SIGNED))+1)
					FROM {$wpdb->postmeta} pm1,  {$wpdb->postmeta} pm2
					WHERE pm1.meta_key='{$order_number_meta_name}'
					AND pm2.meta_key='_order_number_formatted' AND LEFT( pm2.meta_value, 6 ) = '%s'",
				$post_id, $today );

			$success = $wpdb->query( $query );
				
			if ( $success ) {
				// on success, set the formatted order number
				$order_number = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", $wpdb->insert_id ) );

				update_post_meta( $post_id, '_order_number_formatted', $this->format_order_number( $order_number, $order_number_prefix, $order_number_suffix, $order_number_length, $post_id ) );

				// save the order number configuration at the time of creation, so the integer part can be renumbered at a later date if needed
				$order_number_meta = array(
					'prefix' => $order_number_prefix,
					'suffix' => $order_number_suffix,
					'length' => $order_number_length,
				);
				update_post_meta( $post_id, '_order_number_meta', $order_number_meta );
			}
		}
		return $success;
	}


	/**
	 * Filter to return our _order_number_formatted field rather than the post ID,
	 * for display.
	 *
	 * @param string $order_number the order id with a leading hash
	 * @param WC_Order $order the order object
	 *
	 * @return string custom order number, with leading hash
	 */
	public function get_order_number( $order_number, $order ) {

		// maintain the hash?
		$maybe_hash = $this->get_has_hash_before_order_number() ? _x( '#', 'hash before order number', self::TEXT_DOMAIN ) : '';

		// can't trust $order->order_custom_fields object
		$order_number_formatted = get_post_meta( $order->id, '_order_number_formatted', true );
		if ( $order_number_formatted ) {
			return $maybe_hash . $order_number_formatted;
		}

		// return a 'draft' order number that will not be saved to the db (this
		//  means that when adding an order from the admin, the order number you
		//  first see may not be the one you end up with, but it's better than the
		//  alternative of showing the underlying post id)
		$post_status = isset( $order->post_status ) ? $order->post_status : get_post_status( $order->id );
		if ( 'auto-draft' == $post_status ) {
			global $wpdb;
			$order_number_start = get_option( 'woocommerce_order_number_start' );
			$order_number = $wpdb->get_var( $wpdb->prepare( "
				SELECT IF(MAX(CAST(meta_value AS SIGNED)) IS NULL OR MAX(CAST(meta_value AS SIGNED)) < %d, %d, MAX(CAST(meta_value AS SIGNED))+1)
				FROM {$wpdb->postmeta}
				WHERE meta_key='_order_number'",
				$order_number_start, $order_number_start ) );
			return $maybe_hash . $this->format_order_number( $order_number, $this->get_order_number_prefix(), $this->get_order_number_suffix(), $this->get_order_number_length(), $order->id ) . ' (' . __( 'Draft', self::TEXT_DOMAIN ) . ')';
		}
		return $order_number;
	}


	/** Admin filters ******************************************************/


	/**
	 * Admin order table orderby ID operates on our meta integral _order_number
	 *
	 * @param array $vars associative array of orderby parameteres
	 *
	 * @return array associative array of orderby parameteres
	 */
	public function woocommerce_custom_shop_order_orderby( $vars ) {
		global $typenow, $wp_query;
		if ( 'shop_order' != $typenow ) return $vars;

		return $this->custom_orderby( $vars );
	}


	/**
	 * Mofifies the given $args argument to sort on our meta integral _order_number
	 *
	 * @since 1.5
	 * @param array $vars associative array of orderby parameteres
	 * @return array associative array of orderby parameteres
	 */
	public function custom_orderby( $args ) {
		// Sorting
		if ( isset( $args['orderby'] ) && 'ID' == $args['orderby'] ) {
			$args = array_merge( $args, array(
				'meta_key' => '_order_number',  // sort on numerical portion for better results
				'orderby'  => 'meta_value_num',
			) );
		}

		return $args;
	}


	/**
	 * Add our custom _order_number_formatted to the set of search fields so that
	 * the admin search functionality is maintained
	 *
	 * @param array $search_fields array of post meta fields to search by
	 *
	 * @return array of post meta fields to search by
	 */
	public function custom_search_fields( $search_fields ) {

		array_push( $search_fields, '_order_number_formatted' );

		return $search_fields;
	}


	/**
	 * Validate the order number start setting, by verifying that
	 * $newvalue is an integer and bigger than the greatest existing order
	 * number
	 *
	 * @param string $newvalue the new value to set
	 * @param string $oldvalue the previous value
	 * @return string $newvalue if it is a positive integer, $oldvalue otherwise
	 */
	public function validate_order_number_start_setting( $newvalue, $oldvalue ) {

		global $wpdb;

		// no change to starting order number
		if ( (int) $newvalue === (int) $oldvalue ) {

			// $newvalue can include left hand zero padding to set a number length, update the value if that is all that's changed
			update_option( 'woocommerce_order_number_length', strlen( $newvalue ) );

			return $newvalue;
		}

		if ( $this->is_order_number_start_invalid( $newvalue ) || $this->is_order_number_start_in_use( $newvalue ) ) {

			// bad value
			return $oldvalue;
		}

		// $newvalue can include left hand zero padding to set a number length, update this value first in case nothing else changed
		update_option( 'woocommerce_order_number_length', strlen( $newvalue ) );

		// good value, and remove any padding zeroes
		return $newvalue;
	}


	/**
	 * Filter to add the settings error message, if needed, and remove
	 * it from the $location url otherwise
	 *
	 * pre WC 2.1 only
	 *
	 * @param string $location url
	 * @param int $status
	 *
	 * @return string the location to redirect to
	 */
	public function add_settings_error_msg( $location, $status) {

		// pre WC 2.1 method only
		if ( SV_WC_Plugin_Compatibility::is_wc_version_gte_2_1() ) {
			return $location;
		}

		if ( $this->errors ) {
			$location = add_query_arg( array( 'wc_error' => urlencode( $this->errors ) ), $location );
		} elseif( strpos( $location, urlencode( __( 'Order Number Start must be a number greater than or equal to 0', self::TEXT_DOMAIN ) ) ) !== false ||
			      strpos( $location, urlencode( __( 'There is an existing order', self::TEXT_DOMAIN ) ) ) !== false ) {
			// otherwise if my error is currently in the url, remove it.  This must be done because it will be kept in the url by the settings form wp_nonce_field() call
			$location = remove_query_arg( 'wc_error', $location );
		}

		return $location;
	}


	/**
	 * Add any settings error
	 *
	 * @since 1.6
	 */
	public function add_settings_errors() {

		global $wpdb;

		// nothing doing
		if ( ! isset( $_POST['woocommerce_order_number_start'] ) ) {
			return;
		}

		$newvalue = $_POST['woocommerce_order_number_start'];
		$oldvalue = get_option( 'woocommerce_order_number_start' );

		// no change to starting order number
		if ( (int) $newvalue === (int) $oldvalue ) {
			return;
		}

		if ( $this->is_order_number_start_invalid( $newvalue ) ) {

			// bad value
			if ( SV_WC_Plugin_Compatibility::is_wc_version_gte_2_1() ) {
				WC_Admin_Settings::add_error( __( 'Order Number Start must be a number greater than or equal to 0.', self::TEXT_DOMAIN ) );
			} else {
				$this->errors = __( 'Order Number Start must be a number greater than or equal to 0.', self::TEXT_DOMAIN );
			}

			return;
		}

		if ( $this->is_order_number_start_in_use( $newvalue ) ) {

			// existing order number with a greater incrementing value
			$post_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_order_number' AND meta_value = %d", $this->get_max_order_number() ) );

			if ( class_exists( 'WC_Order' ) ) {
				$order = new WC_Order( $post_id );
				$highest_order_number = $order->get_order_number();
			} else {
				$highest_order_number = $post_id;
			}

			if ( SV_WC_Plugin_Compatibility::is_wc_version_gte_2_1() ) {
				WC_Admin_Settings::add_error( sprintf( __( 'There is an existing order (%s) with a number greater than or equal to %s.  To set a new order number start please choose a higher number or permanently delete the relevant order(s).', self::TEXT_DOMAIN ), $highest_order_number, (int) $newvalue ) );
			} else {
				$this->errors = sprintf( __( 'There is an existing order (%s) with a number greater than or equal to %s.  To set a new order number start please choose a higher number or permanently delete the relevant order(s).', self::TEXT_DOMAIN ), $highest_order_number, (int) $newvalue );
			}

			return;
		}
	}


	/**
	 * Inject our admin settings into the Settings > General page
	 *
	 * @param array $settings associative-array of WooCommerce settings
	 * @return array associative-array of WooCommerce settings
	 */
	public function admin_settings( $settings ) {

		$updated_settings = array();

		foreach ( $settings as $section ) {

			$updated_settings[] = $section;

			// New section after the "General Options" section
			if ( isset( $section['id'] ) && 'general_options' == $section['id'] &&
				 isset( $section['type'] ) && 'sectionend' == $section['type'] ) {

				$updated_settings[] = array( 'name' => __( 'Order Numbers', self::TEXT_DOMAIN ), 'type' => 'title', 'desc' => '', 'id' => 'order_number_options' );

				$updated_settings[] = array(
					'name'     => __( 'Order Number Start', self::TEXT_DOMAIN ),
					'desc_tip' => sprintf( __( 'The starting number for the incrementing portion of the order numbers, unless there is an existing order with a higher number.  Use leading zeroes to pad your order numbers to a desired minimum length.  Any newly placed orders will be numbered like: %s', self::TEXT_DOMAIN ), $this->format_order_number( get_option( 'woocommerce_order_number_start' ), $this->get_order_number_prefix(), $this->get_order_number_suffix(), $this->get_order_number_length() ) ),
					'id'       => 'woocommerce_order_number_start',
					'type'     => 'text',
					'css'      => 'min-width:300px;',
					'default'  => '',
					'desc'     => sprintf( __( 'Sample order number: %s', self::TEXT_DOMAIN ), '<span id="sample_order_number">' . $this->format_order_number( get_option( 'woocommerce_order_number_start' ), $this->get_order_number_prefix(), $this->get_order_number_suffix(), $this->get_order_number_length() ) . '</span>' ),
				);

				$updated_settings[] = array(
					'name'     => __( 'Hash Before Order number ', self::TEXT_DOMAIN ),
					'id'       => 'woocommerce_hash_before_order_number',
					'type'     => 'checkbox',
					'css'      => 'min-width:300px;',
					'default'  => 'yes',
					'desc'     => __( 'Display a hash (#) before order numbers on frontend and admin.', self::TEXT_DOMAIN ),
				);

				$updated_settings[] = array(
					'name'     => __( 'Order Number Prefix', self::TEXT_DOMAIN ),
					'desc_tip' => __( 'Set your custom order number prefix.  You may use {DD}, {MM}, {YYYY} for the current day, month and year respectively.', self::TEXT_DOMAIN ),
					'id'       => 'woocommerce_order_number_prefix',
					'type'     => 'text',
					'css'      => 'min-width:300px;',
					'default'  => '',
					'desc'     => sprintf( __( 'See the %splugin documentation%s for the full set of available patterns.', self::TEXT_DOMAIN ), '<a target="_blank" href="http://wcdocs.woothemes.com/user-guide/extensions/functionality/sequential-order-numbers/">', '</a>' ),
				);

				$updated_settings[] = array(
					'name'     => __( 'Order Number Suffix', self::TEXT_DOMAIN ),
					'desc_tip' => __( 'Set your custom order number suffix.  You may use {DD}, {MM}, {YYYY} for the current day, month and year respectively.', self::TEXT_DOMAIN ),
					'id'       => 'woocommerce_order_number_suffix',
					'type'     => 'text',
					'css'      => 'min-width:300px;',
					'default'  => '',
				);

				$updated_settings[] = array(
					'name'     => __( 'Skip Free Orders', self::TEXT_DOMAIN ),
					'desc'     => __( 'Skip order numbers for free orders', self::TEXT_DOMAIN ),
					'desc_tip' => __( 'With this enabled an order number will not be assigned to an order consisting solely of free products.', self::TEXT_DOMAIN ),
					'id'       => 'woocommerce_order_number_skip_free_orders',
					'type'     => 'checkbox',
					'css'      => 'min-width:300px;',
					'default'  => 'no',
				);

				$updated_settings[] = array(
					'name'     => __( 'Free Order Identifer', self::TEXT_DOMAIN ),
					'desc'     => sprintf( __( 'Example free order identifier: %s', self::TEXT_DOMAIN ), '<span id="sample_free_order_number">' . $this->format_order_number( $this->get_free_order_number_start(), $this->get_free_order_number_prefix() ) . '</span>' ),
					'desc_tip' => __( 'The text to display in place of the order number for free orders.  This will be displayed anywhere an order number would otherwise be shown: to the customer, in emails, and in the admin.', self::TEXT_DOMAIN ),
					'id'       => 'woocommerce_free_order_number_prefix',
					'type'     => 'text',
					'css'      => 'min-width:300px;',
					'default'  => __( 'FREE-', self::TEXT_DOMAIN ),
				);

				$updated_settings[] = array( 'type' => 'sectionend', 'id' => 'order_number_options' );
			}
		}
		return $updated_settings;
	}


	/**
	 * Render the admin settings javascript which will live-update the sample
	 * order number for improved feedback when configuring
	 *
	 * @since 1.3
	 */
	public function admin_settings_js() {

		// our options are on the general tab
		if ( isset( $_REQUEST['tab'] ) && $_REQUEST['tab'] != 'general' ) {
			return;
		}

		ob_start(); ?>
		var free_order_number_start = <?php echo $this->get_free_order_number_start(); ?>;
		$('#woocommerce_order_number_skip_free_orders').change(function() {
			if ( ! $( this ).is( ':checked' ) ) {
				$( '#woocommerce_free_order_number_prefix' ).closest( 'tr' ).hide();
			} else {
				$( '#woocommerce_free_order_number_prefix' ).closest( 'tr' ).show();
			}
		}).change();

		$( '#woocommerce_free_order_number_prefix' ).on( 'keyup change input', function() {
			$( '#sample_free_order_number' ).text( formatOrderNumber( free_order_number_start, $( this ).val() ) );
		} );

		$('#woocommerce_order_number_start, #woocommerce_order_number_prefix, #woocommerce_order_number_suffix').on('keyup change input',
			function() {
				$( '#sample_order_number' ).text( formatOrderNumber( $( '#woocommerce_order_number_start' ).val(), $( '#woocommerce_order_number_prefix' ).val(), $( '#woocommerce_order_number_suffix' ).val() ) );
			}
		);
		function formatOrderNumber( orderNumber, orderNumberPrefix, orderNumberSuffix ) {

			orderNumberPrefix = ( typeof orderNumberPrefix === "undefined" ) ? "" : orderNumberPrefix;
			orderNumberSuffix = ( typeof orderNumberSuffix === "undefined" ) ? "" : orderNumberSuffix;

			var formatted = orderNumberPrefix + orderNumber + orderNumberSuffix;

			var d = new Date();
			if ( formatted.indexOf( '{D}' )    > -1) formatted = formatted.replace( '{D}',    d.getDate() );
			if ( formatted.indexOf( '{DD}' )   > -1) formatted = formatted.replace( '{DD}',   leftPad( d.getDate().toString(), 2, '0' ) );
			if ( formatted.indexOf( '{M}' )    > -1) formatted = formatted.replace( '{M}',    d.getMonth() + 1 );
			if ( formatted.indexOf( '{MM}' )   > -1) formatted = formatted.replace( '{MM}',   leftPad( ( d.getMonth() + 1 ).toString(), 2, '0' ) );
			if ( formatted.indexOf( '{YY}' )   > -1) formatted = formatted.replace( '{YY}',   ( d.getFullYear() ).toString().substr( 2 ) );
			if ( formatted.indexOf( '{YYYY}' ) > -1) formatted = formatted.replace( '{YYYY}', d.getFullYear() );
			if ( formatted.indexOf( '{H}' )  > -1) formatted = formatted.replace( '{H}',  d.getHours() );
			if ( formatted.indexOf( '{HH}' ) > -1) formatted = formatted.replace( '{HH}', leftPad( d.getHours().toString(), 2, '0' ) );
			if ( formatted.indexOf( '{N}' )  > -1) formatted = formatted.replace( '{N}',  d.getMinutes() );
			if ( formatted.indexOf( '{S}' )  > -1) formatted = formatted.replace( '{S}',  d.getSeconds() );

			return formatted;
		}
		function leftPad( value, count, char ) {
			while ( value.length < count ) {
				value = char + value;
			}
			return value;
		}
		<?php
		$javascript = ob_get_clean();
		SV_WC_Plugin_Compatibility::wc_enqueue_js( $javascript );
	}


	/**
	 * CSV Customer/Order Export Plugin filter to add additional columns of data
	 *
	 * @since 1.5
	 * @param array $ret array of columns and data values
	 */
	public function export_csv_extra_columns( $ret ) {

		// the "formatted" order number is already exported by the CSV plugin as the "Order ID"
		// TODO: support the free orders
		$ret['columns'][] = 'Order Number';
		$ret['data'][]    = '_order_number';

		return $ret;
	}


	/**
	 * Sets an order number on a subscriptions-created order
	 *
	 * @since 1.5
	 *
	 * @param WC_Order $renewal_order the new renewal order object
	 * @param WC_Order $original_order the original order object
	 * @param int $product_id the product post identifier
	 * @param string $new_order_role the role the renewal order is taking, one of 'parent' or 'child'
	 */
	public function subscriptions_set_sequential_order_number( $renewal_order, $original_order, $product_id, $new_order_role ) {
		$order_post = get_post( $renewal_order->id );
		$this->set_sequential_order_number( $order_post->ID, $order_post );
	}


	/**
	 * Don't copy over order number meta when creating a parent or child renewal order
	 *
	 * @since 1.5
	 *
	 * @param array $order_meta_query query for pulling the metadata
	 * @param int $original_order_id Post ID of the order being used to purchased the subscription being renewed
	 * @param int $renewal_order_id Post ID of the order created for renewing the subscription
	 * @param string $new_order_role The role the renewal order is taking, one of 'parent' or 'child'
	 * @return string
	 */
	public function subscriptions_remove_renewal_order_meta( $order_meta_query, $original_order_id, $renewal_order_id, $new_order_role ) {

		$order_meta_query .= " AND meta_key NOT IN ( '_order_number', '_order_number_formatted', '_order_number_free', '_order_number_meta' )";

		return $order_meta_query;
	}


	/** Helper methods ******************************************************/


	/**
	 * Returns the max order number currently in use
	 *
	 * @since 1.6
	 * @return int maximum order number
	 */
	private function get_max_order_number() {

		global $wpdb;

		if ( ! is_null( $this->max_order_number ) ) {
			return $this->max_order_number;
		}

		return $this->max_order_number = $wpdb->get_var( "SELECT MAX( CAST( meta_value AS SIGNED ) ) FROM $wpdb->postmeta WHERE meta_key='_order_number'" );
	}


	/**
	 * Returns true if the given order number start value is already in use,
	 * false otherwise
	 *
	 * @since 1.6
	 * @param string $value order number start
	 * @return boolean true if the given order number start value is already in use
	 */
	private function is_order_number_start_in_use( $value ) {

		// check for an existing order number with a greater incrementing value
		$order_number = $this->get_max_order_number();

		return ! is_null( $order_number ) && (int) $order_number >= $value;
	}


	/**
	 * Returns false if the given order number start value is invalid, true otherwise
	 *
	 * @since 1.6
	 * @param string $value order number start value
	 * @return boolean true if the given order number start value is invalid
	 */
	private function is_order_number_start_invalid( $value ) {
		return ! ctype_digit( $value ) || (int) $value != $value;
	}


	/**
	 * Returns $order_number formatted with the order number prefix/
	 * postfix, if set
	 *
	 * @param int $order_number incrementing portion of the order number
	 * @param string $order_number_prefix optional order number prefix string
	 * @param string $order_number_suffix optional order number suffix string
	 * @param int $order_number_length optional order number length
	 *
	 * @return string formatted order number
	 */
	private function format_order_number( $order_number, $order_number_prefix = '', $order_number_suffix = '', $order_number_length = 1, $order_id = 0 ) {

		$order_number = (int) $order_number;

		// any order number padding?
		if ( $order_number_length && ctype_digit( $order_number_length ) ) {
			$order_number = sprintf( "%0{$order_number_length}d", $order_number );
		}

		$formatted = $order_number_prefix . $order_number . $order_number_suffix;
		
		// UPDATE Bart Veldhuizen
		// get email address for this order
		if( $order_id != 0 ) {
			$email = get_post_meta( $order_id, '_billing_email', true );
			$email = strtoupper( substr( $email, 0, 3 ) );
		} else {
			$email = 'XXX';
		}

		// pattern substitution
		$replacements = array(
			'{D}'    => date_i18n( 'j' ),
			'{DD}'   => date_i18n( 'd' ),
			'{M}'    => date_i18n( 'n' ),
			'{MM}'   => date_i18n( 'm' ),
			'{YY}'   => date_i18n( 'y' ),
			'{YYYY}' => date_i18n( 'Y' ),
			'{H}'    => date_i18n( 'G' ),
			'{HH}'   => date_i18n( 'H' ),
			'{N}'    => date_i18n( 'i' ),
			'{S}'    => date_i18n( 's' ),
			'{EMAIL}'=> $email
		);

		return str_replace( array_keys( $replacements ), $replacements, $formatted );
	}


	/**
	 * Returns true if the order number should be displayed with a leading hash (#)
	 * Defaults to true
	 *
	 * @return boolean true if the order number should be displayed with a leading hash
	 */
	private function get_has_hash_before_order_number() {
		return 'yes' == get_option( 'woocommerce_hash_before_order_number', 'yes' );
	}


	/**
	 * Returns the order number prefix, if set
	 *
	 * @return string order number prefix
	 */
	private function get_order_number_prefix() {

		if ( ! isset( $this->order_number_prefix ) ) {
			$this->order_number_prefix = get_option( 'woocommerce_order_number_prefix', "" );
		}

		return $this->order_number_prefix;
	}


	/**
	 * Returns the order number suffix, if set
	 *
	 * @return string order number suffix
	 */
	private function get_order_number_suffix() {

		if ( ! isset( $this->order_number_suffix ) ) {
			$this->order_number_suffix = get_option( 'woocommerce_order_number_suffix', "" );
		}

		return $this->order_number_suffix;
	}


	/**
	 * Returns the order number length, defaulting to 1 if not set
	 *
	 * @since 1.3
	 *
	 * @return string order number length
	 */
	private function get_order_number_length() {

		if ( ! isset( $this->order_number_length ) ) {
			$this->order_number_length = get_option( 'woocommerce_order_number_length', 1 );
		}

		return $this->order_number_length;
	}


	/**
	 * Returns true if order numbers should be skipped for orders consisting
	 * solely of free products
	 *
	 * @since 1.3
	 *
	 * @return boolean true if order numbers should be skipped for free orders
	 */
	private function skip_free_orders() {
		return 'yes' == get_option( 'woocommerce_order_number_skip_free_orders', 'no' );
	}


	/**
	 * Returns the value to use in place of the order number for free orders
	 * when 'skip free orders' is enabled
	 *
	 * @since 1.3
	 *
	 * @return string text to use in place of the order number for free orders
	 */
	private function get_free_order_number_prefix() {
		return get_option( 'woocommerce_free_order_number_prefix', __( 'FREE-', self::TEXT_DOMAIN ) );
	}


	/**
	 * Gets the free order number incrementing piece
	 *
	 * @since 1.3
	 *
	 * @return int free order number incrementing portion
	 */
	private function get_free_order_number_start() {
		return get_option( 'woocommerce_free_order_number_start' );
	}


	/**
	 * Returns true if this order consists entirely of free products AND
	 * has a total of 0 (so no shipping charges or other fees)
	 *
	 * @since 1.3
	 *
	 * @param int $order_id order identifier
	 * @return boolean true if the order consists solely of free products
	 */
	private function is_free_order( $order_id ) {

		$is_free = true;
		$order = new WC_Order( $order_id );

		// easy check: order total
		if ( $order->get_total() > 0 ) {
			$is_free = false;
		}

		// free order
		return apply_filters( 'wc_sequential_order_numbers_is_free_order', $is_free, $order_id );
	}


	/**
	 * Returns the plugin name, localized
	 *
	 * @since 1.6
	 * @see SV_WC_Plugin::get_plugin_name()
	 * @return string the plugin name
	 */
	public function get_plugin_name() {
		return __( 'WooCommerce Sequential Order Numbers Pro', self::TEXT_DOMAIN );
	}


	/**
	 * Returns __FILE__
	 *
	 * @since 1.6
	 * @see
	 * @return string the full path and filename of the plugin file
	 */
	protected function get_file() {
		return __FILE__;
	}


	/**
	 * Gets the plugin documentation url, which defaults to:
	 * http://docs.woothemes.com/document/woocommerce-{dasherized plugin id}/
	 *
	 * @since 1.6
	 * @see SV_WC_Plugin::get_documentation_url()
	 * @return string documentation URL
	 */
	public function get_documentation_url() {
		return 'http://docs.woothemes.com/document/sequential-order-numbers/';
	}


	/**
	 * Gets the plugin configuration URL
	 *
	 * @since 1.6
	 * @see SV_WC_Plugin::get_settings_url()()
	 * @see SV_WC_Plugin::get_settings_link()
	 * @param string $plugin_id optional plugin identifier.  Note that this can be a
	 *        sub-identifier for plugins with multiple parallel settings pages
	 *        (ie a gateway that supports both credit cards and echecks)
	 * @return string plugin settings URL
	 */
	public function get_settings_url( $plugin_id = null ) {
		return SV_WC_Plugin_Compatibility::get_general_configuration_url();
	}


	/**
	 * Returns true if on the admin tab configuration page
	 *
	 * @since 1.6
	 * @see SV_WC_Plugin::is_plugin_settings()
	 * @return boolean true if on the admin plugin settings page
	 */
	public function is_plugin_settings() {
		return SV_WC_Plugin_Compatibility::is_general_configuration_page();
	}


	/** Lifecycle methods ******************************************************/


	/**
	 * Run every time.  Used since the activation hook is not executed when updating a plugin
	 *
	 * @see SV_WC_Plugin::install()
	 */
	protected function install() {

		if ( false === get_option( 'woocommerce_order_number_start' ) ) {
			// initial installation (can't use woocommerce_sequential_order_numbers_pro_version unfortunately as older versions of the plugins didn't use this option)

			// if the free sequential order numbers plugin is installed and active, deactivate it
			if ( $this->is_plugin_active( 'woocommerce-sequential-order-numbers/woocommerce-sequential-order-numbers.php' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
				deactivate_plugins( 'woocommerce-sequential-order-numbers/woocommerce-sequential-order-numbers.php' );
			}

			// initial install, set the order number for all existing orders to the post id:
			//  page through the "publish" orders in blocks to avoid out of memory errors
			$offset         = (int) get_option( 'wc_sequential_order_numbers_pro_install_offset', 0 );
			$posts_per_page = 500;
			do {
				// grab a set of order ids
				$order_ids = get_posts( array( 'post_type' => 'shop_order', 'fields' => 'ids', 'offset' => $offset, 'posts_per_page' => $posts_per_page ) );

				// some sort of bad database error: deactivate the plugin and display an error
				if ( is_wp_error( $order_ids ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
					deactivate_plugins( 'woocommerce-sequential-order-numbers-pro/woocommerce-sequential-order-numbers.php' );  // hardcode the plugin path so that we can use symlinks in development

					wp_die( sprintf( __( 'Error activating and installing <strong>WooCommerce Sequential Order Numbers Pro</strong>: %s', self::TEXT_DOMAIN ), '<ul><li>' . implode( '</li><li>', $order_ids->get_error_messages() ) . '</li></ul>' ) .
					        '<a href="' . admin_url( 'plugins.php' ) . '">' . __( '&laquo; Go Back', self::TEXT_DOMAIN ) . '</a>' );
				}

				// otherwise go through the results and set the order numbers
				if ( is_array( $order_ids ) ) {
					foreach( $order_ids as $order_id ) {

						$order_number           = get_post_meta( $order_id, '_order_number', true );
						$order_number_formatted = get_post_meta( $order_id, '_order_number_formatted', true );

						if ( '' === $order_number && '' === $order_number_formatted ) {
							// pre-existing order, set _order_number/_order_number_formatted
							add_post_meta( $order_id, '_order_number',           $order_id );
							add_post_meta( $order_id, '_order_number_formatted', $order_id );
						} elseif ( '' === $order_number_formatted ) {
							// an order from the free sequential order number plugin, add the _order_number_formatted field
							add_post_meta( $order_id, '_order_number_formatted', $order_number );
						}
					}
				}

				// increment offset
				$offset += $posts_per_page;
				// and keep track of how far we made it in case we hit a script timeout
				update_option( 'wc_sequential_order_numbers_pro_install_offset', $offset );
			} while( count( $order_ids ) == $posts_per_page );  // while full set of results returned  (meaning there may be more results still to retrieve)

			// set the best order number start value that we can
			global $wpdb;
			$order_number = (int) $wpdb->get_var( "SELECT MAX(CAST(meta_value AS SIGNED)) FROM {$wpdb->postmeta} WHERE meta_key='_order_number'" );
			add_option( 'woocommerce_order_number_start', $order_number ? $order_number + 1 : 1 );
		}

		// free order number index
		if ( false === get_option( 'woocommerce_free_order_number_start' ) ) {
			add_option( 'woocommerce_free_order_number_start', 1 );
		}
	}


	/**
	 * Handles upgrades
	 *
	 * @since 1.5
	 * @see SV_WC_Plugin::upgrade()
	 * @param string $installed_version the currently installed version
	 */
	protected function upgrade( $installed_version ) {

		// hash before order number option was added in 1.5
		if ( version_compare( $installed_version, '1.5', '<' ) ) {
			add_option( 'woocommerce_hash_before_order_number', 'yes' );
		}

		// in this version we dropped the order_number_length frontend setting in favor of accepting zero padding right in the order number start value
		if ( version_compare( $installed_version, '1.5.5', '<' ) ) {

			$order_number_start  = get_option( 'woocommerce_order_number_start' );
			$order_number_length = get_option( 'woocommerce_order_number_length' );

			if ( $order_number_length > strlen( $order_number_start ) ) {

				// option 1: an order number length is configured which is longer than the size of the current order number,
				//  update the order number start value to include the padding so it renders correctly on the frontend
				update_option( 'woocommerce_order_number_start', $this->format_order_number( $order_number_start, '', '', $order_number_length ) );

			} elseif ( strlen( $order_number_start ) > $order_number_length ) {

				// option 2: starting order number is longer than the configured order number length, so update the length setting
				update_option( 'woocommerce_order_number_length', strlen( $order_number_start ) );
			}

		}
	}

} // end WC_Seq_Order_Number_Pro class


/**
 * The WC_Seq_Order_Number_Pro global object
 * @name $wc_seq_order_number_pro
 * @global WC_Seq_Order_Number_Pro $GLOBALS['wc_seq_order_number_pro']
 */
$GLOBALS['wc_seq_order_number_pro'] = new WC_Seq_Order_Number_Pro();

} // init_woocommerce_sequential_order_numbers_pro()
