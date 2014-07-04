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
 * @package     WC-Gateway-Braintree/Templates
 * @author      SkyVerge
 * @copyright   Copyright (c) 2012-2014, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Template Function Overrides
 */

if ( ! function_exists( 'woocommerce_braintree_my_cards' ) ) :

	/**
	 * Template function to render the "My Cards" section
	 *
	 * @since 2.0
	 */
	function woocommerce_braintree_my_cards( $gateway ) {
		global $wc_braintree;

		if ( ! $gateway->is_available() ) {
			return;
		}

		// process payment method actions
		if ( ! empty( $_GET['braintree-cc-token'] ) && ! empty( $_GET['braintree-action'] ) && ! empty( $_GET['_wpnonce'] ) ) {

			// security check
			if ( false === wp_verify_nonce( $_GET['_wpnonce'], 'braintree-security' ) ) {
				wp_die( __( 'There was an error with your request, please try again.', WC_Braintree::TEXT_DOMAIN ) );
			}

			$braintree_cc_token = sanitize_text_field( $_GET['braintree-cc-token'] );

			// handle deletion
			if ( 'delete' === $_GET['braintree-action'] ) {
				$gateway->delete_saved_card( $braintree_cc_token );
			}

			// handle default change
			if ( 'set-default' === $_GET['braintree-action'] ) {
				$gateway->set_default_saved_card( $braintree_cc_token );
			}
		}

		// get available saved payment methods
		$cards = $gateway->get_saved_cards( get_current_user_id() );

		SV_WC_Plugin_Compatibility::wc_get_template(
			'myaccount/braintree-my-cards.php',
			array(
				'cards'   => $cards,
				'gateway' => $gateway,

			),
			'',
			$wc_braintree->get_plugin_path() . '/templates/'
		);

		// Add confirm javascript when deleting cards
		ob_start();
		?>
			$( 'a.braintree-delete-saved-card' ).click( function ( e ) {
				if ( ! confirm( '<?php _e( 'Are you sure you want to delete this card?', WC_Braintree::TEXT_DOMAIN ); ?>' ) ) {
					e.preventDefault();
				}
			} );
		<?php
		SV_WC_Plugin_Compatibility::wc_enqueue_js( ob_get_clean() );
	}

endif;

if ( ! function_exists( 'woocommerce_braintree_payment_fields' ) ) :

	/**
	 * Template function to render the payment fields on checkout
	 *
	 * @since 2.0
	 */
	function woocommerce_braintree_payment_fields( $gateway ) {
		global $wc_braintree;

		if ( $gateway->description ) {
			echo '<p>' . wp_kses_post( $gateway->description ) . '</p>';
		}

		if ( ! $gateway->is_production() ) {

			echo '<p>' . __( 'Sandbox/Development/QA Mode Active', WC_Braintree::TEXT_DOMAIN ) . '</p>';
			echo '<p>' . sprintf( __( 'Use test credit cards Visa: %s or Amex: %s', WC_Braintree::TEXT_DOMAIN ), '4111111111111111', '378282246310005' ) . '</p>';
		}

		$cards = ( is_user_logged_in() ) ? $gateway->get_saved_cards( get_current_user_id() ) : array();
		$has_cards = ( ! empty( $cards ) ) ? true: false;

		SV_WC_Plugin_Compatibility::wc_get_template(
			'checkout/braintree-payment-fields.php',
			array(
				'has_cards'    => $has_cards,
				'cards'        => $cards,
				'cvv_required' => $gateway->is_cvv_required(),
				'gateway'      => $gateway,
			),
			'',
		  $wc_braintree->get_plugin_path() . '/templates/'
		);
	}

endif;
