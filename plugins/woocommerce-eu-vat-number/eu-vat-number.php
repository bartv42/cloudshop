<?php
/*
Plugin Name: WooCommerce EU VAT Number
Plugin URI: http://woothemes.com/woocommerce
Description: Lets you collect (and validate) a customers EU VAT number during checkout. EU businesses with a valid VAT number are not charged VAT. Uses http://isvat.appspot.com/ for VAT number validation.
Version: 1.6.0
Author: WooThemes
Author URI: http://woothemes.com
Requires at least: 3.1
Tested up to: 3.2

	Copyright: © 2009-2011 WooThemes.
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) )
	require_once( 'woo-includes/woo-functions.php' );

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), 'd2720c4b4bb8d6908e530355b7a2d734', '18592' );

if ( is_woocommerce_active() ) {

	/**
	 * Localisation
	 **/
	load_plugin_textdomain( 'wc_eu_vat_number', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	/**
	 * woocommerce_sale_flash_pro class
	 **/
	if ( ! class_exists( 'WC_EU_VAT_Number' ) ) {

		class WC_EU_VAT_Number {

			var $checkout_title;
			var $checkout_message;
			var $vat_number 				= '';
			var $is_eu_vat_number 		    = false;
			var $country;
			var $validation_api_url 		= 'http://woo-vat-validator.herokuapp.com/v1/validate/';
			var $european_union_countries 	= array('AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GB', 'GR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'IM', 'MC' );

			/**
			 * __construct function.
			 *
			 * @access public
			 * @return void
			 */
			public function __construct() {

				$this->checkout_title   = get_option( 'woocommerce_eu_vat_number_checkout_title' );
				$this->checkout_message = get_option( 'woocommerce_eu_vat_number_checkout_message' );

				// Init settings
				$this->settings = array(
					array(
						'name' 		=> __( 'EU VAT Title', 'wc_eu_vat_number' ),
						'desc' 		=> __( 'The title that appears at checkout above the VAT Number box.', 'wc_eu_vat_number' ),
						'id' 		=> 'woocommerce_eu_vat_number_checkout_title',
						'type' 		=> 'text'
					),
					array(
						'name' 		=> __( 'EU VAT Message', 'wc_eu_vat_number' ),
						'desc' 		=> __( 'The message that appears at checkout above the VAT Number box.', 'wc_eu_vat_number' ),
						'id' 		=> 'woocommerce_eu_vat_number_checkout_message',
						'type' 		=> 'textarea',
						'css'		=> 'width:100%; height: 100px;'
					),
					array(
						'name' 		=> __( 'Show field for base country', 'wc_eu_vat_number' ),
						'desc' 		=> __( 'Show the VAT field even when the customer is in your base country', 'wc_eu_vat_number' ),
						'id' 		=> 'woocommerce_eu_vat_number_show_in_base',
						'type' 		=> 'checkbox'
					),
					array(
						'name' 		=> __( 'Deduct VAT for base country', 'wc_eu_vat_number' ),
						'desc' 		=> __( 'Deduct the VAT even when the customer is in your base country and has a valid number', 'wc_eu_vat_number' ),
						'id' 		=> 'woocommerce_eu_vat_number_deduct_in_base',
						'type' 		=> 'checkbox'
					),
					array(
						'name' 		=> __( 'Store non-valid numbers', 'wc_eu_vat_number' ),
						'desc' 		=> __( 'Enable this option to store numbers which don\'t pass validation, rather than reject them. Tax will not be made exempt.', 'wc_eu_vat_number' ),
						'id' 		=> 'woocommerce_eu_vat_number_store_invalid_numbers',
						'type' 		=> 'checkbox'
					),
				);

				// Default options
				add_option( 'woocommerce_eu_vat_number_checkout_title', __( "EU VAT Number", 'wc_eu_vat_number' ) );
				add_option( 'woocommerce_eu_vat_number_checkout_message', __( "If you are in the EU and have a valid EU VAT registration number please enter it below.", 'wc_eu_vat_number' ) );
				add_option( 'woocommerce_eu_vat_number_show_in_base', 'yes' );
				add_option( 'woocommerce_eu_vat_number_deduct_in_base', 'no' );
				add_option( 'woocommerce_eu_vat_number_store_invalid_numbers', 'no' );

				// Admin
				add_action( 'woocommerce_settings_tax_options_end', array( $this, 'admin_settings' ) );
				add_action( 'woocommerce_update_options_tax', array( $this, 'save_admin_settings' ) );

			   	// Actions/Filters
				add_action( 'woocommerce_after_order_notes', array( $this, 'vat_number_field' ) );

				add_action( 'woocommerce_checkout_update_order_review', array( $this, 'ajax_update_checkout_totals' ) ); // Check during ajax update totals
				add_action( 'woocommerce_checkout_process', array( $this, 'process_checkout' ) ); // Check during checkout process

				add_action( 'woocommerce_checkout_update_user_meta', array( $this, 'update_user_meta' ) );
				add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'update_order_meta' ) );

				// Email meta
				add_filter( 'woocommerce_email_order_meta_keys', array( $this, 'order_meta_keys' ) );

		    }

	        /*-----------------------------------------------------------------------------------*/
			/* Class Functions */
			/*-----------------------------------------------------------------------------------*/

			/**
			 * admin_settings function.
			 *
			 * @access public
			 * @return void
			 */
			public function admin_settings() {
				woocommerce_admin_fields( $this->settings );
			}

			/**
			 * save_admin_settings function.
			 *
			 * @access public
			 * @return void
			 */
			public function save_admin_settings() {
				woocommerce_update_options( $this->settings );
			}

			/**
			 * vat_number_field function.
			 *
			 * @access public
			 * @param mixed $woocommerce_checkout
			 * @return void
			 */
			public function vat_number_field( $woocommerce_checkout ) {
				global $woocommerce;

				echo '<div id="woocommerce_eu_vat_number"><h3>' . wptexturize( __( $this->checkout_title, 'wc_eu_vat_number' ) ) . '</h3>';
				echo wpautop( '<small>' . wptexturize( __( $this->checkout_message, 'wc_eu_vat_number' ) ) . '</small>');

				woocommerce_form_field( 'vat_number', array(
					'type' 			=> 'text',
					'class' 		=> array( 'vat-number update_totals_on_change address-field form-row-wide' ),
					'label' 		=> __( 'VAT Number', 'wc_eu_vat_number' ),
					'placeholder' 	=> __( 'VAT Number', 'wc_eu_vat_number' ),
				) );

				echo '</div>';

				$inline_js = "
					jQuery('select#billing_country').live('change', function(){

					var country = jQuery('select#billing_country').val();
				";

				if ( get_option( 'woocommerce_eu_vat_number_show_in_base' ) == 'yes' ) {

					$inline_js .= "var check_countries = new Array(\"" . implode( '","', $this->european_union_countries ) . "\");";

				} else {

					$inline_js .= "var check_countries = new Array(\"".implode( '","', array_diff( $this->european_union_countries, array( $woocommerce->countries->get_base_country() ) ) )."\");";

				}

				$inline_js .= "
					if (country && jQuery.inArray( country, check_countries ) >= 0) {
						jQuery('#woocommerce_eu_vat_number').fadeIn();
					} else {
						jQuery('#woocommerce_eu_vat_number').fadeOut();
						jQuery('#woocommerce_eu_vat_number input').val('');
					}

					}).change();
				";

				if ( function_exists( 'wc_enqueue_js' ) ) {
					wc_enqueue_js( $inline_js );
				} else {
					$woocommerce->add_inline_js( $inline_js );
				}
			}

			/**
			 * ajax_update_checkout_totals function.
			 *
			 * @access public
			 * @param mixed $form_data
			 * @return void
			 */
			public function ajax_update_checkout_totals( $form_data ) {
				global $woocommerce;

				parse_str( $form_data );

				if ( ! empty( $vat_number ) && ! empty( $billing_country ) ) {
					// Its set
					$this->country 		= woocommerce_clean( $billing_country );
					$this->vat_number 	= woocommerce_clean( $vat_number );

					if ( $this->check_vat_number_validity() ) {

						// Check base and billing is in the EU
						if ( in_array( $woocommerce->countries->get_base_country(), $this->european_union_countries ) && in_array( $this->country, $this->european_union_countries ) ) {

							if ( $woocommerce->countries->get_base_country() == $this->country && get_option( 'woocommerce_eu_vat_number_deduct_in_base' ) == 'yes' ) {

								$woocommerce->customer->set_is_vat_exempt( true );
								return;

							} elseif ( $woocommerce->countries->get_base_country() != $this->country ) {

								$woocommerce->customer->set_is_vat_exempt( true );
								return;

							}

						}

					}

				}

				$woocommerce->customer->set_is_vat_exempt( false );
			}

			/**
			 * process_checkout function.
			 *
			 * @access public
			 * @return void
			 */
			public function process_checkout() {
				global $woocommerce;

				$this->country 		= $woocommerce->customer->get_country();
				$this->vat_number 	= isset( $_POST['vat_number'] ) ? woocommerce_clean( $_POST['vat_number'] ) : '';

				if ( $this->check_vat_number_validity( true ) ) {

					// Check base and billing is in the EU
					if ( in_array( $woocommerce->countries->get_base_country(), $this->european_union_countries ) && in_array( $this->country, $this->european_union_countries ) ) {

						if ( $woocommerce->countries->get_base_country() == $this->country && get_option( 'woocommerce_eu_vat_number_deduct_in_base' ) == 'yes' ) {

							$woocommerce->customer->set_is_vat_exempt( true );
							return;

						} elseif ( $woocommerce->countries->get_base_country() != $this->country ) {

							$woocommerce->customer->set_is_vat_exempt( true );
							return;

						}
					}
				}
			}

			/**
			 * check_vat_number_validity function.
			 *
			 * @access public
			 * @return void
			 */
			public function check_vat_number_validity( $on_checkout = false ) {
				global $woocommerce;

				$this->is_eu_vat_number = false;

				// Check vars
				if ( ! $this->country || ! $this->vat_number ) {
					return false;
				}

				// Check country
				if ( ! in_array( $this->country, $this->european_union_countries ) ) {
					$error = sprintf( __( 'You cannot use a VAT number since your country (%s) is outside of the EU.', 'wc_eu_vat_number' ), $this->country );
					if ( function_exists( 'wc_add_notice' ) ) {
						wc_add_notice( $error, 'error' );
					} else {
						$woocommerce->add_error( $error );
					}
					$this->vat_number = '';
					return false;
				}

				// Format the number
				$this->vat_number = strtoupper( str_replace( array( ' ', '-', '_', '.' ), '', $this->vat_number ) );

				// Remove country code if set at the begining
				$first_chars = substr( $this->vat_number, 0, 2 );

				if ( in_array( $first_chars, array_merge( $this->european_union_countries, array( 'EL' ) ) ) )
					$this->vat_number = substr( $this->vat_number, 2 );

				if ( ! $this->vat_number )
					return false;

				$vat_prefix = $this->get_vat_number_prefix( $this->country );

				// Check cache
				$cached_result = wp_cache_get( 'vat_number_' . $vat_prefix . $this->vat_number );

				if ( false == $cached_result ) {

					$response = wp_remote_get( $this->validation_api_url . $vat_prefix . '/' . $this->vat_number . '/' );

					if ( is_wp_error( $response ) || empty( $response['body'] ) ) {

						$this->is_eu_vat_number = true;

						// There was an error with the API so let the number pass to prevent the order being cancelled
						return true;

					} else {

						//$result = json_decode( $response['body'] );

						//echo '<pre>' . $response['body'] . '</pre>';

						if ( $response['body'] == "true" ) {

							$this->is_eu_vat_number = true;

							wp_cache_set( 'vat_number_' . $vat_prefix . $this->vat_number, 1 );
							
							return true;

						} else {

							if ( get_option( 'woocommerce_eu_vat_number_store_invalid_numbers' ) != 'yes' ) {

								$error = sprintf(__('You have entered an invalid VAT number (%s) for your country.', 'wc_eu_vat_number'), $this->vat_number );

								if ( $on_checkout ) {
									if ( function_exists( 'wc_add_notice' ) ) {
										wc_add_notice( $error, 'error' );
									} else {
										$woocommerce->add_error( $error );
									}
								}

							}

							return false;
						}
					}

				} else {

					$this->is_eu_vat_number = true;

					// Only valid numbers are cached so this is valid
					return true;
				}
			}

			/**
			 * update_user_meta function.
			 *
			 * @access public
			 * @param mixed $user_id
			 * @return void
			 */
			public function update_user_meta( $user_id ) {
				if ( $this->vat_number ) {
					update_user_meta( $user_id, $this->vat_number, true );
				}
			}

			/**
			 * update_order_meta function.
			 *
			 * @access public
			 * @param mixed $order_id
			 * @return void
			 */
			public function update_order_meta( $order_id ) {
				if ( $this->vat_number ) {
					if ( $this->is_eu_vat_number ) {
						update_post_meta( $order_id, 'VAT Number', $this->get_vat_number_prefix( $this->country ) . ' ' . $this->vat_number );
						update_post_meta( $order_id, 'Valid EU VAT Number', 'true' );
					} else {
						update_post_meta( $order_id, 'VAT Number', $this->vat_number );
						update_post_meta( $order_id, 'Valid EU VAT Number', 'false' );
					}
				}
			}

			/**
			 * Return the vat number prefix
			 *
			 * @param  string $country
			 * @return string
			 */
			public function get_vat_number_prefix( $country ) {
				$vat_prefix = $country;

				// Greece has to be a pain
				switch ( $country ) {
					case 'GR' :
						$vat_prefix = 'EL';
					break;
					case 'IM' :
						$vat_prefix = 'GB';
					break;
					case 'MC' :
						$vat_prefix = 'FR';
					break;
				}

				return $vat_prefix;
			}

			/**
			 * order_meta_keys function.
			 *
			 * @access public
			 * @param mixed $keys
			 * @return void
			 */
			public function order_meta_keys( $keys ) {
				$keys[] = 'VAT Number';
				return $keys;
			}

		}

		$WC_EU_VAT_Number = new WC_EU_VAT_Number();
	}
}