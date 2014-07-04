<?php
/**
 * WooCommerce Gateway Braintree
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Gateway Braintree to newer
 * versions in the future. If you wish to customize WooCommerce Gateway Braintree for your
 * needs please refer to http://docs.woothemes.com/document/braintree/ for more information.
 *
 * @package     WC-Gateway-Braintree/Classes
 * @author      SkyVerge
 * @copyright   Copyright (c) 2012-2014, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Handles all single purchases and payments-related actions
 * Extended by the Addons class to provide subscriptions/pre-orders support
 *
 * @since 2.0
 * @extends \WC_Payment_Gateway
 */
class WC_Gateway_Braintree extends WC_Payment_Gateway {


	/** @var string braintree environment */
	public $environment;

	/** @var string braintree merchant ID */
	protected $merchant_id;

	/** @var string braintree merchant account ID */
	protected $merchant_account_id;

	/** @var string braintree public key */
	protected $public_key;

	/** @var string braintree private key */
	protected $private_key;

	/** @var string braintree client-side encryption (CSE) key */
	protected $cse_key;

	/** @var string submit transactions for settlement immediately */
	public $settlement;

	/** @var string require the card security code during checkout */
	public $require_cvv;

	/** @var array card types to show images for */
	public $card_types;

	/** @var string 4 options for debug mode - off, checkout, log, both */
	public $debug_mode;


	/**
	 * Load payment gateway and related settings
	 *
	 * @since 2.0
	 * @return \WC_Gateway_Braintree
	 */
	public function __construct() {

		$this->id                 = 'braintree';
		$this->method_title       = __( 'Braintree', WC_Braintree::TEXT_DOMAIN );
		$this->method_description = __( 'Allow customers to securely save their credit card to their account for use with single purchases, pre-orders, and subscriptions.', WC_Braintree::TEXT_DOMAIN );

		$this->supports = array( 'products' );

		$this->has_fields = true;

		$this->icon = apply_filters( 'wc_braintree_icon', '' );

		// Load the form fields
		$this->init_form_fields();

		// Load the settings
		$this->init_settings();

		// Define user set variables
		foreach ( $this->settings as $setting_key => $setting ) {
			$this->$setting_key = $setting;
		}

		// pay page fallback
		add_action( 'woocommerce_receipt_' . $this->id, create_function( '$order', 'echo "<p>" . __( "Thank you for your order.", WC_Braintree::TEXT_DOMAIN ) . "</p>";' ) );

		// save settings
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		// load & configure API
		$this->configure_api();

		// add braintree.js / checkout javascript
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_js' ) );
	}


	/**
	 * Enqueues the required braintree.js library and custom checkout javascript. Also localizes credit card
	 * validation errors
	 *
	 * @since 2.0
	 */
	public function enqueue_js() {
		global $wc_braintree;

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// load braintree.js library
		wp_enqueue_script( 'braintree-js', 'https://js.braintreegateway.com/v1/braintree.js', array( 'wc-checkout' ), '1.3.8', true );

		// load checkout script
		wp_enqueue_script( 'wc-braintree', $wc_braintree->get_plugin_url() . '/assets/js/frontend/wc-braintree' . $suffix . '.js', array( 'braintree-js' ), WC_Braintree::VERSION, true );

		// add CSE key and localize error messages
		$params = array(
			'cse_key'               => $this->get_cse_key(),
			'card_number_missing'   => __( 'Credit card number is missing', WC_Braintree::TEXT_DOMAIN ),
			'card_number_invalid'   => __( 'Credit card number is invalid', WC_Braintree::TEXT_DOMAIN ),
			'cvv_missing'           => __( 'Card security code is missing', WC_Braintree::TEXT_DOMAIN ),
			'cvv_invalid'           => __( 'Card security code is invalid (only digits allowed)', WC_Braintree::TEXT_DOMAIN ),
			'cvv_length_invalid'    => __( 'Card security code is invalid (must be 3 or 4 digits)', WC_Braintree::TEXT_DOMAIN ),
			'card_exp_date_invalid' => __( 'Card expiration date is invalid', WC_Braintree::TEXT_DOMAIN ),
		);

		wp_localize_script( 'wc-braintree', 'braintree_params', $params );
	}


	/**
	 * Initialize payment gateway settings fields
	 *
	 * @since 2.0
	 */
	public function init_form_fields() {

		$this->form_fields = apply_filters( 'wc_braintree_form_fields', array(

			'enabled' => array(
				'title'       => __( 'Enable / Disable', WC_Braintree::TEXT_DOMAIN ),
				'label'       => __( 'Check this to enable or disable the gateway.', WC_Braintree::TEXT_DOMAIN ),
				'type'        => 'checkbox',
				'default'     => 'no',
			),

			'title' => array(
				'title'       => __( 'Title', WC_Braintree::TEXT_DOMAIN ),
				'type'        => 'text',
				'desc_tip'    => __( 'Payment method title that the customer will see during checkout.', WC_Braintree::TEXT_DOMAIN ),
				'default'     => __( 'Credit Card', WC_Braintree::TEXT_DOMAIN ),
			),

			'description' => array(
				'title'       => __( 'Description', WC_Braintree::TEXT_DOMAIN ),
				'type'        => 'textarea',
				'desc_tip'    => __( 'Payment method description that the customer will see during checkout.', WC_Braintree::TEXT_DOMAIN ),
				'default'     => __( 'Pay securely using your credit card.', WC_Braintree::TEXT_DOMAIN ),
			),

			'environment' => array(
				'title'       => __( 'Environment', WC_Braintree::TEXT_DOMAIN ),
				'type'        => 'select',
				'description' => __( 'What environment do you want your transactions posted to?', WC_Braintree::TEXT_DOMAIN ),
				'default'     => 'production',
				'options'     => array(
					'development' => __( 'Development', WC_Braintree::TEXT_DOMAIN ),
					'sandbox'     => __( 'Sandbox', WC_Braintree::TEXT_DOMAIN ),
					'production'  => __( 'Production', WC_Braintree::TEXT_DOMAIN ),
					'qa'          => __( 'Quality Assurance', WC_Braintree::TEXT_DOMAIN ),
				),
			),

			'merchant_id' => array(
				'title'       => __( 'Merchant ID', WC_Braintree::TEXT_DOMAIN ),
				'type'        => 'text',
				'desc_tip'    => __( 'The Merchant ID for your Braintree account.', WC_Braintree::TEXT_DOMAIN ),
				'default'     => '',
			),

			'merchant_account_id' => array(
				'title'    => __( 'Merchant Account ID', WC_Braintree::TEXT_DOMAIN ),
				'type'     => 'text',
				'desc_tip' => __( 'Optional merchant account ID. Leave blank to use your default merchant account, or if you have multiple accounts specify which one to use for processing transactions.', WC_Braintree::TEXT_DOMAIN ),
				'default'  => '',
			),

			'public_key' => array(
				'title'       => __( 'Public Key', WC_Braintree::TEXT_DOMAIN ),
				'type'        => 'text',
				'desc_tip'    => __( 'The Public Key for your Braintree account.', WC_Braintree::TEXT_DOMAIN ),
				'default'     => '',
			),

			'private_key' => array(
				'title'       => __( 'Private Key', WC_Braintree::TEXT_DOMAIN ),
				'type'        => 'password',
				'desc_tip'    => __( 'The Private Key for your Braintree account.', WC_Braintree::TEXT_DOMAIN ),
				'default'     => '',
			),

			'cse_key' => array(
				'title'       => __( 'Client-Side Encryption Key', WC_Braintree::TEXT_DOMAIN ),
				'type'        => 'textarea',
				'desc_tip'    => __( 'The Client-Side Encryption Key for your Braintree account.', WC_Braintree::TEXT_DOMAIN ),
				'default'     => '',
				'css'         => 'max-width: 300px;',
			),

			'settlement' => array(
				'title'   => __( 'Submit for Settlement?', WC_Braintree::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this to submit all transactions for settlement immediately.', WC_Braintree::TEXT_DOMAIN ),
				'default' => 'yes',
			),

			'require_cvv' => array(
				'title'       => __( 'Card Verification (CV2)', WC_Braintree::TEXT_DOMAIN ),
				'label'       => __( 'Require customers to enter credit card verification code (CV2) during checkout.', WC_Braintree::TEXT_DOMAIN ),
				'type'        => 'checkbox',
				'default'     => 'no',
			),

			'card_types' => array(
				'title'       => __( 'Accepted Card Logos', WC_Braintree::TEXT_DOMAIN ),
				'type'        => 'multiselect',
				'desc_tip'    => __( 'Select which card types you accept to display the logos for on your checkout page.  This is purely cosmetic and optional, and will have no impact on the cards actually accepted by your account.', WC_Braintree::TEXT_DOMAIN ),
				'default'     => array( 'VISA', 'MC', 'AMEX', 'DISC' ),
				'class'       => 'chosen_select',
				'css'         => 'width: 350px;',
				'options'     => apply_filters( 'wc_braintree_card_types',
					array(
						'VISA'   => 'Visa',
						'MC'     => 'MasterCard',
						'AMEX'   => 'American Express',
						'DISC'   => 'Discover',
					)
				)
			),

			'debug_mode' => array(
				'title'       => __( 'Debug Mode', WC_Braintree::TEXT_DOMAIN ),
				'type'        => 'select',
				'desc_tip'    => __( 'Show Detailed Error Messages and API requests / responses on the checkout page and/or save them to the log for debugging purposes.', WC_Braintree::TEXT_DOMAIN ),
				'default'     => 'off',
				'options' => array(
					'off'      => __( 'Off', WC_Braintree::TEXT_DOMAIN ),
					'checkout' => __( 'Show on Checkout Page', WC_Braintree::TEXT_DOMAIN ),
					'log'      => __( 'Save to Log', WC_Braintree::TEXT_DOMAIN ),
					'both'     => __( 'Both', WC_Braintree::TEXT_DOMAIN )
				),
			),

		) );
	}


	/**
	 * Checks for proper gateway configuration (required fields populated, etc)
	 * and that there are no missing dependencies
	 *
	 * @since 2.0
	 */
	public function is_available() {
		global $wc_braintree;

		// is enabled check
		$is_available = parent::is_available();

		// proper configuration
		if ( ! $this->get_merchant_id() || ! $this->get_public_key() || ! $this->get_private_key() || ! $this->get_cse_key() ) {
			$is_available = false;
		}

		// all dependencies met
		if ( count( $wc_braintree->get_missing_dependencies() ) > 0 ) {
			$is_available = false;
		}

		return apply_filters( 'wc_gateway_braintree_is_available', $is_available );
	}


	/**
	 * Add selected card icons to payment method label, defaults to Visa/MC/Amex/Discover
	 *
	 * @since 2.0
	 */
	public function get_icon() {
		global $wc_braintree;

		$icon = '';

		if ( $this->icon ) {

			// use icon provided by filter
			$icon = '<img src="' . esc_url( SV_WC_Plugin_Compatibility::force_https_url( $this->icon ) ) . '" alt="' . esc_attr( $this->title ) . '" />';

		} elseif ( ! empty( $this->card_types ) ) {

			// display icons for the selected card types
			foreach ( $this->card_types as $card_type ) {

				if ( is_readable( $wc_braintree->get_plugin_path() . '/assets/images/card-' . strtolower( $card_type ) . '.png' ) ) {
					$icon .= '<img src="' . esc_url( SV_WC_Plugin_Compatibility::force_https_url( $wc_braintree->get_plugin_url() ) . '/assets/images/card-' . strtolower( $card_type ) . '.png' ) . '" alt="' . esc_attr( strtolower( $card_type ) ) . '" />';
				}
			}
		}

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}


	/**
	 * Display the payment fields on the checkout page
	 *
	 * @since 2.0
	 */
	public function payment_fields() {

		woocommerce_braintree_payment_fields( $this );
	}


	/**
	 * Handles payment for guest / registered user checkout, using this logic:
	 *
	 * + If customer is logged in or creating an account, add them as a customer and save their card to the vault (if
	 * they don't already exist and they're using a new card), and process the transaction
	 *
	 * + If customer is a guest, create a single transaction and don't create a customer or save the card to the vault
	 *
	 * @since 2.0
	 * @param int $order_id the ID of the order
	 * @return array|void
	 */
	public function process_payment( $order_id ) {

		// get WC_Order object and add braintree-specific info
		$order = $this->get_order( $order_id );

		try {

			/* registered customer checkout (already logged in or creating account at checkout) */
			if ( is_user_logged_in() || 0 != $order->user_id ) {

				// create new braintree customer if needed
				if ( empty( $order->braintree_order['customerId'] ) ) {
					$order = $this->create_customer( $order );
				}

				// save card in vault if customer is using new card
				if ( empty( $order->braintree_order['paymentMethodToken'] ) ) {
					$order = $this->create_credit_card( $order );
				}

				// payment failures are handled internally by do_transaction()
				if ( $this->do_transaction( $order ) ) {

					// mark order as having received payment
					$order->payment_complete();

					SV_WC_Plugin_Compatibility::WC()->cart->empty_cart();

					return array(
						'result'   => 'success',
						'redirect' => $this->get_return_url( $order ),
					);
				}

			/* guest checkout */
			} else {

				// payment failures are handled internally by do_guest_transaction()
				if ( $this->do_guest_transaction( $order ) ) {

					// mark order as having received payment
					$order->payment_complete();

					SV_WC_Plugin_Compatibility::WC()->cart->empty_cart();

					return array(
						'result'   => 'success',
						'redirect' => $this->get_return_url( $order ),
					);
				}
			}

		} catch ( WC_Gateway_Braintree_Exception $e ) {

			// mark order as failed, which adds an order note for the admin and displays a generic "payment error" to the customer
			$this->mark_order_as_failed( $order, $e->getMessage() );

			// add detailed debugging information
			$this->add_debug_message( $e->getErrors() );

		} catch ( Braintree_Exception_Authorization $e ) {

			$this->mark_order_as_failed( $order, __( 'Authorization failed, ensure that your API key is correct and has permissions to create transactions.', WC_Braintree::TEXT_DOMAIN ) );

		} catch ( Exception $e ) {

			$this->mark_order_as_failed( $order, sprintf( __( 'Error Type %s', WC_Braintree::TEXT_DOMAIN ), get_class( $e ) ) );
		}
	}


	/**
	 * Build the braintree order array needed to process transactions
	 *
	 * @since 2.0
	 * @param int $order_id order ID being processed
	 * @return \WC_Order instance
	 */
	protected function get_order( $order_id ) {

		$order = new WC_Order( $order_id );

		// get customer ID if exists
		$braintree_customer_id = get_user_meta( $order->user_id, '_wc_braintree_customer_id', true );

		// get selected payment token
		$braintree_cc_token = $this->get_post( 'braintree-cc-token' );

		// setup order array for use by braintree SDK
		$order->braintree_order = array(
			'amount'  => number_format( $order->get_total(), 2, '.', '' ),
			'orderId' => ltrim( $order->get_order_number(), _x( '#', 'hash before the order number', WC_Braintree::TEXT_DOMAIN ) ),
			'options' => array( 'submitForSettlement' => $this->submit_for_settlement() ),
		);

		// add merchant account ID if populated
		if ( $this->get_merchant_account_id() ) {
			$order->braintree_order['merchantAccountId'] = $this->get_merchant_account_id();
		}

		// add customer ID
		if ( $braintree_customer_id ) {
			$order->braintree_order['customerId'] = $braintree_customer_id;
		}
		
		// add payment token
		if ( $braintree_cc_token ) {
			$order->braintree_order['paymentMethodToken'] = $braintree_cc_token;
		}

		// add shipping address if populated
		if ( ! empty( $order->shipping_country ) ) {

			$order->braintree_order['shipping'] = array(
				'firstName'         => $order->shipping_first_name,
				'lastName'          => $order->shipping_last_name,
				'company'           => $order->shipping_company,
				'streetAddress'     => $order->shipping_address_1,
				'extendedAddress'   => $order->shipping_address_2,
				'locality'          => $order->shipping_city,
				'region'            => $order->shipping_state,
				'postalCode'        => $order->shipping_postcode,
				'countryCodeAlpha2' => $order->shipping_country,
			);

			// save shipping address to vault if user logged in or creating account
			if ( is_user_logged_in() || 0 !== $order->user_id ) {
				$order->braintree_order['options']['storeShippingAddressInVault'] = true;
			}
		}

		return apply_filters( 'wc_braintree_get_order', $order, $this );
	}


	/**
	 * Create a customer in braintree and add their card to the vault
	 *
	 * @since 2.0
	 * @param \WC_Order $order
	 * @throws WC_Gateway_Braintree_Exception if customer creation or card verification (optional) fails
	 * @return \WC_Order with customer ID & credit card token added
	 */
	protected function create_customer( $order ) {

		// define customer
		$customer = array(
			'firstName' => $order->billing_first_name,
			'lastName'  => $order->billing_last_name,
			'company'   => $order->billing_company,
			'phone'     => $order->billing_phone,
			'email'     => $order->billing_email,
			'creditCard' => array(
				'number'          => $this->get_post( 'number' ),
				'expirationMonth' => $this->get_post( 'month' ),
				'expirationYear'  => $this->get_post( 'year' ),
				'cardholderName'  => $order->billing_first_name . ' ' . $order->billing_last_name,
				'billingAddress' => array(
					'firstName'         => $order->billing_first_name,
					'lastName'          => $order->billing_last_name,
					'company'           => $order->billing_company,
					'streetAddress'     => $order->billing_address_1,
					'extendedAddress'   => $order->billing_address_2,
					'locality'          => $order->billing_city,
					'region'            => $order->billing_state,
					'postalCode'        => $order->billing_postcode,
					'countryCodeAlpha2' => $order->billing_country,
				),
				'options' => array(
					'verifyCard' => true,
				),
			),
		);

		// add CVV
		if ( $this->is_cvv_required() ) {
			$customer['creditCard']['cvv'] = $this->get_post( 'cvv' );
		}

		// create customer and add card to vault
		$response = Braintree_Customer::create( apply_filters( 'wc_braintree_create_customer', $customer, $order ) );

		// check for success
		if ( $response->success ) {

			$order->braintree_order['customerId']         = $response->customer->id;
			$order->braintree_order['paymentMethodToken'] = $response->customer->creditCards[0]->token;

			// always add customer ID and token to order
			update_post_meta( $order->id, '_wc_braintree_customer_id', $order->braintree_order['customerId'] );
			update_post_meta( $order->id, '_wc_braintree_cc_token',    $order->braintree_order['paymentMethodToken'] );

			// add customer ID to user if logged in or creating account
			if ( is_user_logged_in() || 0 !== $order->user_id )  {
				add_user_meta( $order->user_id, '_wc_braintree_customer_id', $order->braintree_order['customerId'] );
			}

		} else {

			// failed to create customer or card verification failed (if requested)
			throw new WC_Gateway_Braintree_Exception( 'customer', $response );
		}

		return $order;
	}


	/**
	 * Creates a saved card in the vault for an existing braintree customer
	 *
	 * @since 2.0
	 * @param \WC_Order $order
	 * @throws WC_Gateway_Braintree_Exception if credit card creation or card verification (optional) fails
	 * @return \WC_Order with credit card token added
	 */
	protected function create_credit_card( $order ) {

		// define credit card info
		$credit_card = array(
			'customerId' => $order->braintree_order['customerId'],
			'cardholderName'  => $order->billing_first_name . ' ' . $order->billing_last_name,
			'number'          => $this->get_post( 'number' ),
			'expirationMonth' => $this->get_post( 'month' ),
			'expirationYear'  => $this->get_post( 'year' ),
			'billingAddress' => array(
				'firstName'         => $order->billing_first_name,
				'lastName'          => $order->billing_last_name,
				'company'           => $order->billing_company,
				'streetAddress'     => $order->billing_address_1,
				'extendedAddress'   => $order->billing_address_2,
				'locality'          => $order->billing_city,
				'region'            => $order->billing_state,
				'postalCode'        => $order->billing_postcode,
				'countryCodeAlpha2' => $order->billing_country,
			),
			'options' => array(
				'verifyCard' => true,
				'failOnDuplicatePaymentMethod' => true,
			),
		);

		// add CVV
		if ( $this->is_cvv_required() ) {
			$credit_card['cvv'] = $this->get_post( 'cvv' );
		}

		// create customer and add card to vault
		$response = Braintree_CreditCard::create( apply_filters( 'wc_braintree_create_credit_card', $credit_card, $order ) );

		// check for success
		if ( $response->success ) {

			$order->braintree_order['paymentMethodToken'] = $response->creditCard->token;

			// always add credit card token to order
			update_post_meta( $order->id, '_wc_braintree_cc_token', $order->braintree_order['paymentMethodToken'] );

		} else {

			// failed to create credit card or card verification failed (if requested)
			throw new WC_Gateway_Braintree_Exception( 'credit_card', $response );
		}

		return $order;
	}


	/**
	 * Process transaction for customer, either logged in or creating an account at checkout. A customer is created
	 * in braintree if they don't already exists, and the card is saved to the vault
	 *
	 * @since 2.0
	 * @param \WC_Order $order
	 * @throws WC_Gateway_Braintree_Exception if transaction creation fails or transaction is declined
	 * @return bool true if transaction was successful, false otherwise
	 */
	protected function do_transaction( $order ) {

		// add CVV
		if ( $this->is_cvv_required() ) {
			$order->braintree_order['creditCard']['cvv'] = $this->get_post( 'cvv' );
		}

		$response = Braintree_Transaction::sale( $order->braintree_order );

		// check for transaction success
		if ( $response->success ) {

			// add order note
			$order->add_order_note( sprintf( __( 'Braintree Transaction Approved (ID: %s) - %s ending in %s (expires %s)', WC_Braintree::TEXT_DOMAIN ),
					$response->transaction->id, $response->transaction->creditCardDetails->cardType,
					$response->transaction->creditCardDetails->last4, $response->transaction->creditCardDetails->expirationDate )
			);

			// save transaction info as order meta
			update_post_meta( $order->id, '_wc_braintree_trans_id',       $response->transaction->id );
			update_post_meta( $order->id, '_wc_braintree_trans_env',      $this->get_environment() );
			update_post_meta( $order->id, '_wc_braintree_card_type',      $response->transaction->creditCardDetails->cardType );
			update_post_meta( $order->id, '_wc_braintree_card_last_four', $response->transaction->creditCardDetails->last4 );
			update_post_meta( $order->id, '_wc_braintree_card_exp_date',  $response->transaction->creditCardDetails->expirationDate );

			// update customer ID and credit card token
			update_post_meta( $order->id, '_wc_braintree_customer_id',    $response->transaction->customerDetails->id );
			update_post_meta( $order->id, '_wc_braintree_cc_token',       $response->transaction->creditCardDetails->token );

			// update braintree customer ID saved to user
			update_user_meta( $order->user_id, '_wc_braintree_customer_id', $response->transaction->customerDetails->id );

			return true;

		} else {

			// transaction failed
			throw new WC_Gateway_Braintree_Exception( 'transaction', $response );
		}
	}


	/**
	 * Process transaction using the payment info provided at checkout. Designed for guest checkouts, as no customer
	 * is created in braintree and the credit card is not saved
	 *
	 * @since 2.0
	 * @param \WC_Order $order
	 * @throws WC_Gateway_Braintree_Exception if transaction creation fails or transaction is declined
	 * @return bool true if transaction was successful, false otherwise
	 */
	private function do_guest_transaction( $order ) {

		// add payment info
		$order->braintree_order['creditCard'] = array(
			'cardholderName'  => $order->billing_first_name . ' ' . $order->billing_last_name,
			'number'          => $this->get_post( 'number' ),
			'expirationMonth' => $this->get_post( 'month' ),
			'expirationYear'  => $this->get_post( 'year' ),
		);

		// add CVV
		if ( $this->is_cvv_required() ) {
			$order->braintree_order['creditCard']['cvv'] = $this->get_post( 'cvv' );
		}

		// add billing info
		$order->braintree_order['billing'] = array(
			'firstName'         => $order->billing_first_name,
			'lastName'          => $order->billing_last_name,
			'company'           => $order->billing_company,
			'streetAddress'     => $order->billing_address_1,
			'extendedAddress'   => $order->billing_address_2,
			'locality'          => $order->billing_city,
			'region'            => $order->billing_state,
			'postalCode'        => $order->billing_postcode,
			'countryCodeAlpha2' => $order->billing_country,
		);

		// add customer info
		$order->braintree_order['customer'] = array(
			'firstName' => $order->billing_first_name,
			'lastName'  => $order->billing_last_name,
			'email'     => $order->billing_email,
		);

		// submit transaction
		$response = Braintree_Transaction::sale( $order->braintree_order );

		// check for transaction success
		if ( $response->success ) {

			// add order note
			$order->add_order_note( sprintf( __( 'Braintree Transaction Approved  (ID: %s) : %s ending in %s (expires %s)', WC_Braintree::TEXT_DOMAIN ),
				$response->transaction->id, $response->transaction->creditCardDetails->cardType,
				$response->transaction->creditCardDetails->last4, $response->transaction->creditCardDetails->expirationDate )
			);

			// save transaction info as order meta
			add_post_meta( $order->id, '_wc_braintree_trans_id',       $response->transaction->id );
			add_post_meta( $order->id, '_wc_braintree_trans_env',      $this->get_environment() );
			add_post_meta( $order->id, '_wc_braintree_card_type',      $response->transaction->creditCardDetails->cardType );
			add_post_meta( $order->id, '_wc_braintree_card_last_four', $response->transaction->creditCardDetails->last4 );
			add_post_meta( $order->id, '_wc_braintree_card_exp_date',  $response->transaction->creditCardDetails->expirationDate );

			return true;

		} else {

			// transaction failed
			throw new WC_Gateway_Braintree_Exception( 'transaction', $response );
		}
	}


	/**
	 * Mark the given order as failed and set the order note
	 *
	 * @since 2.0
	 * @param object $order the \WC_Order object
	 * @param string $message a message to display inside the "Payment Failed" order note
	 */
	protected function mark_order_as_failed( $order, $message = '' ) {

		$order_note = sprintf( __( 'Braintree Transaction Failed (%s)', WC_Braintree::TEXT_DOMAIN ), $message );

		// Mark order as failed if not already set, otherwise, make sure we add the order note so we can detect when someone fails to check out multiple times
		if ( 'failed' != $order->status ) {
			$order->update_status( 'failed', $order_note );
		} else {
			$order->add_order_note( $order_note );
		}

		// add customer-facing error message
		SV_WC_Plugin_Compatibility::wc_add_notice( __( 'An error occurred, please try again or try an alternate form of payment.', WC_Braintree::TEXT_DOMAIN ), 'error' );

		// add debug message for admin
		$this->add_debug_message( $message );
	}


	/**
	 * Safely get and trim data from $_POST
	 *
	 * @since 2.0
	 * @param string $key array key to get from $_POST array
	 * @return string value from $_POST or blank string if $_POST[ $key ] is not set
	 */
	protected function get_post( $key ) {

		if ( isset( $_POST[ $key ] ) ) {
			return trim( $_POST[ $key ] );
		}

		return '';
	}


	/** Saved card methods ******************************************************/


	/**
	 * Get the saved cards on file for the given user ID.
	 *
	 * @since 2.0
	 * @param int $user_id the WP user ID
	 * @return array an array of braintree creditCard objects
	 */
	public function get_saved_cards( $user_id ) {

		$cards = array();

		if ( $customer_id = get_user_meta( $user_id, '_wc_braintree_customer_id', true ) ) {

			try {

				$customer = Braintree_Customer::find( $customer_id );

				$cards = ( ! empty( $customer->creditCards ) ) ? $customer->creditCards : array();

			} catch ( Exception $e ) {

				$this->add_debug_message( $e->getMessage() );
			}
		}

		return $cards;
	}


	/**
	 * Get the saved credit card object on file for the given token
	 *
	 * @since 2.0
	 * @param string $token the braintree token for the payment method
	 * @return object braintree creditCard object
	 */
	public function get_saved_card( $token ) {

		try {

			return Braintree_CreditCard::find( $token );

		} catch ( Exception $e ) {

			$this->add_debug_message( $e->getMessage() );
		}
	}


	/**
	 * Delete the saved card in braintree given the credit card token
	 *
	 * @since 2.0
	 * @param string $token the braintree credit card token
	 */
	public function delete_saved_card( $token ) {

		try {

			Braintree_CreditCard::delete( $token );

		} catch ( Exception $e ) {

			$this->add_debug_message( $e->getMessage() );
		}
	}


	/**
	 * Set the given credit card token as the default card in braintree. This will appear first in the list
	 * of cards on the My Cards section, and first in the list of saved cards on checkout
	 *
	 * @since 2.0
	 * @param string $token the braintree credit card token
	 */
	public function set_default_saved_card( $token ) {

		try {

			Braintree_CreditCard::update( $token, array( 'options' => array( 'makeDefault' => true ) ) );

		} catch ( Exception $e ) {

			$this->add_debug_message( $e->getMessage() );
		}

	}


	/** Helpers ******************************************************/


	/**
	 * Adds debug messages to the page as a WC message/error, and / or to the WC Error log
	 *
	 * @since 2.0
	 * @param array $errors error messages to add
	 */
	protected function add_debug_message( $errors ) {

		// do nothing when debug mode is off
		if ( 'off' == $this->debug_mode || empty( $errors ) ) {
			return;
		}

		$message = implode( ', ', ( is_array( $errors ) ) ? $errors : array( $errors ) );

		// add debug message to checkout page
		if ( 'checkout' === $this->debug_mode || 'both' === $this->debug_mode ) {
			SV_WC_Plugin_Compatibility::wc_add_notice( $message, 'error' );
		}

		// add debug message to WC error log
		if ( 'log' === $this->debug_mode || 'both' === $this->debug_mode ) {
			$GLOBALS['wc_braintree']->log( $message );
		}
	}


	/**
	 * Load and configure the braintree PHP SDK
	 *
	 * @since 2.0
	 */
	private function configure_api() {
		global $wc_braintree;

		if ( ! $this->is_available() ) {
			return;
		}

		// if SDK is already loaded, bail
		if ( class_exists( 'Braintree_Configuration', false ) ) {
			return;
		}

		// load Braintree PHP SDK
		require_once( $wc_braintree->get_plugin_path() . '/lib/Braintree.php' );

		// configure
		Braintree_Configuration::environment( $this->get_environment() );
		Braintree_Configuration::merchantId( $this->get_merchant_id() );
		Braintree_Configuration::publicKey( $this->get_public_key() );
		Braintree_Configuration::privateKey( $this->get_private_key() );
	}


	/** Getters ******************************************************/


	/**
	 * Return whether transactions should be submitted for settlement immediately or not
	 *
	 * @since 2.0
	 * @return bool, true if transactions should be submitted for settlement immediately, false otherwise
	 */
	public function submit_for_settlement() {

		return ( 'yes' === $this->settlement );
	}


	/**
	 * Return whether transactions should be processed in production environment or not
	 *
	 * @since 2.0
	 * @return bool, true if transactions should be processed in production environment, false otherwise
	 */
	public function is_production() {

		return ( 'production' === $this->get_environment() );
	}


	/**
	 * Return whether the CVV is required or not
	 *
	 * @since 2.0
	 * @return bool, true if the CVV is required, false otherwise
	 */
	public function is_cvv_required() {

		return ( 'yes' === $this->require_cvv );
	}


	/**
	 * Get the Merchant ID
	 *
	 * @since 2.0
	 * @return string merchant ID
	 */
	protected function get_merchant_id() {

		return $this->merchant_id;
	}


	/**
	 * Get the Merchant Account ID
	 *
	 * @since 2.0.5
	 * @return string merchant ID
	 */
	protected function get_merchant_account_id() {

		return apply_filters( 'wc_braintree_get_merchant_account_id', $this->merchant_account_id );
	}


	/**
	 * Get the Public Key
	 *
	 * @since 2.0
	 * @return string public key
	 */
	protected function get_public_key() {

		return $this->public_key;
	}


	/**
	 * Get the Private Key
	 *
	 * @since 2.0
	 * @return string private key
	 */
	protected function get_private_key() {

		return $this->private_key;
	}


	/**
	 * Get the Client-Side Encryption (CSE) Key
	 *
	 * @since 2.0
	 * @return string cse key
	 */
	protected function get_cse_key() {

		return $this->cse_key;
	}


	/**
	 * Get the current environment
	 *
	 * @since 2.0
	 * @return string environment
	 */
	protected function get_environment() {

		return $this->environment;
	}


} // end \WC_Gateway_Braintree class
