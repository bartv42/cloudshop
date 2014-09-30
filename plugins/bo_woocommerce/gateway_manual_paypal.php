<?php
/*
Plugin Name: WooCommerce <enter name> Gateway
Plugin URI: http://woothemes.com/woocommerce
Description: Extends WooCommerce with an <enter name> gateway.
Version: 1.0
Author: WooThemes
Author URI: http://woothemes.com/

	Copyright: 2009-2011 WooThemes.
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

add_action('plugins_loaded', 'bo_woocommerce_gateway_manual_paypal_init', 0);

function bo_woocommerce_gateway_manual_paypal_init() {

	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

	/**
 	 * Localisation
	 */
	load_plugin_textdomain('wc-manual_paypal', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
    
	/**
 	 * Gateway class
 	 */
	class WC_Manual_Paypal extends WC_Payment_Gateway {

	    /**
	     * Constructor for the gateway.
	     */
	    public function __construct() {

			$this->id                 = 'bo_manual_paypal';
			$this->icon               = apply_filters( 'woocommerce_paypal_icon', WC()->plugin_url() . '/assets/images/icons/paypal.png' );
			$this->has_fields         = false;
			$this->method_title       = __( 'Manual Paypal', 'woocommerce' );
			$this->method_description = __( 'Offers manual Paypal handling, similar to bank transactions.', 'woocommerce' );	

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );

			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	    	add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

	    	// Customer Emails
	    	add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );			
		}

	    /**
	     * Initialise Gateway Settings Form Fields
	     */
	    public function init_form_fields() {
	    	$this->form_fields = array(
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Manual PayPal transfers', 'woocommerce' ),
					'default' => 'yes'
				),
				'title' => array(
					'title'       => __( 'Title', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default'     => __( 'Manual PayPal Transfer', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Description', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
					'default'     => __( 'Make your payment directly into our bank account. Please use your Order ID as the payment reference. Your order won\'t be shipped until the funds have cleared in our account.', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'instructions' => array(
					'title'       => __( 'Instructions', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				
				'debug' => array(
					'title'   => __( 'Debug mode', 'woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable limiting to countries and users', 'woocommerce' ),
					'default' => 'yes'
				),
				
				'countries' => array(
					'title'       => __( 'Debug: Countries', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'In which countries is this gateway available? Ex: DE,NL', 'woocommerce' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'customer_ids' => array(
					'title'       => __( 'Debug: Customers', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Enable this option only for specific customers. Enter comma separated Blender IDs', 'woocommerce'),
					'default'     => '',
					'desc_tip'    => true,
				),				
			);
	    }
		
	    /**
	     * Output for the order received page.
	     */
	    public function thankyou_page( $order_id ) {
			if ( $this->instructions ) {
	        	echo wpautop( wptexturize( wp_kses_post( $this->instructions ) ) );
	        }
	    }
		
	    /**
	     * Add content to the WC emails.
	     *
	     * @access public
	     * @param WC_Order $order
	     * @param bool $sent_to_admin
	     * @param bool $plain_text
	     * @return void
	     */
	    public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
	    	if ( ! $sent_to_admin && 'bo_manual_paypal' === $order->payment_method && 'on-hold' === $order->status ) {
				if ( $this->instructions ) {
					echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
				}
			}
	    }		

	    /**
	     * Process the payment and return the result
	     *
	     * @param int $order_id
	     * @return array
	     */
	    public function process_payment( $order_id ) {

			$order = new WC_Order( $order_id );

			// Mark as on-hold (we're awaiting the payment)
			$order->update_status( 'on-hold', __( 'Awaiting Manual PayPal payment', 'woocommerce' ) );

			// Reduce stock levels
			$order->reduce_order_stock();

			// Remove cart
			WC()->cart->empty_cart();

			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);
	    }

        /**
         * Check if this gateway is enabled and available in the user's country and for specific users
         *
         * @access public
         * @return bool
         */
		public function is_available() {

			global $current_user;
			get_currentuserinfo();
		
			$is_available = ( 'yes' === $this->enabled ) ? true : false;

			if( $this->get_option('debug') == 'yes' ) {
				$available_in_countries = $new_arr = array_map('trim', explode(',', $this->get_option( 'countries' )));
				$available_to_customer_ids = $new_arr = array_map('trim', explode(',', $this->get_option( 'customer_ids' )));

				$shipping_country = WC()->customer->get_shipping_country();

				if ( ! in_array( $shipping_country, $available_in_countries ) ) {
					return false;
				}

				if ( ! in_array( $current_user->user_email, $available_to_customer_ids ) ) {
					return false;
				}
			}

			return parent::is_available();
		}

	}
	
	/**
 	* Add the Gateway to WooCommerce
 	**/
	function woocommerce_add_gateway_manual_paypal($methods) {
		$methods[] = 'WC_Manual_Paypal';
		return $methods;
	}
	
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_manual_paypal' );
} 