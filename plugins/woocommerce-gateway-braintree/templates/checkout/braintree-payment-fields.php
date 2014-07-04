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

/**
 * Render the payment fields on checkout
 *
 * @since 2.0
 * @version 2.1.1
 * @param object $gateway the \WC_Gateway_Braintree or \WC_Gateway_Braintree_Addons instance
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>

<style type="text/css">#payment ul.payment_methods li label[for='payment_method_braintree'] img:nth-child(n+2) { margin-left:1px; }</style>
<fieldset>

<?php if ( $has_cards ) : ?>
	<p class="form-row form-row-wide">
		<a class="button" style="float:right;" href="<?php echo get_permalink( woocommerce_get_page_id( 'myaccount' ) ); ?>#braintree-my-saved-cards"><?php _e( 'Manage Saved Cards', WC_Braintree::TEXT_DOMAIN ); ?></a>
		<?php foreach( $cards as $card ) : ?>
			<input type="radio" id="braintree-cc-token-<?php echo esc_attr( $card->token ); ?>" name="braintree-cc-token" style="width:auto;" value="<?php echo esc_attr( $card->token); ?>" <?php checked( $card->isDefault() ); ?>/>
			<label style="display:inline;" for="braintree-cc-token-<?php echo esc_attr( $card->token ); ?>"><?php printf( __( '%s ending in %s (expires %s)', WC_Braintree::TEXT_DOMAIN ), esc_html( $card->cardType ), esc_html( $card->last4 ), esc_html( $card->expirationDate ) ); ?></label><br />
		<?php endforeach; ?>
		<input type="radio" id="braintree-use-new-card" name="braintree-cc-token" <?php checked( $has_cards, false ); ?> style="width:auto;" value="" /> <label style="display:inline;" for="new-card"><?php echo __( 'Use a new credit card', WC_Braintree::TEXT_DOMAIN ); ?></label>
	</p><div class="clear"></div>
<?php endif; ?>

	<div class="<?php echo ( $has_cards ) ? 'braintree-new-card' : 'braintree-payment-form'; ?>">
		<?php

		// credit card number
		woocommerce_form_field( 'braintree-cc-number', array(
			'type'              => 'text',
			'label'             => __( 'Credit Card Number', WC_Braintree::TEXT_DOMAIN ),
			'maxlength'         => 20,
			'required'          => true,
			'custom_attributes' => array( 'autocomplete' => 'off', 'data-encrypted-name' => 'number' ),
			'class'             => array( 'form-row-first', 'validate-cc-number' ),
		) );

		// expiration date
		?>
		<p class="form-row form-row-last validate-cc-exp-date">
			<label for="braintree-cc-exp-date"><?php _e( "Expiration Date", WC_Braintree::TEXT_DOMAIN ); ?> <span class="required">*</span></label>
			<select name="braintree-cc-exp-month" id="braintree-cc-exp-month" class="woocommerce-select woocommerce-cc-month" style="width:auto;" data-encrypted-name="month">
				<option value=""><?php _e( 'Month', WC_Braintree::TEXT_DOMAIN ) ?></option>
				<?php foreach ( range( 1, 12 ) as $month ) : ?>
					<option value="<?php printf( '%02d', $month ) ?>"><?php printf( '%02d', $month ) ?></option>
				<?php endforeach; ?>
			</select>
			<select name="braintree-cc-exp-year" id="braintree-cc-exp-year" class="woocommerce-select woocommerce-cc-year" style="width:auto;" data-encrypted-name="year">
				<option value=""><?php _e( 'Year', WC_Braintree::TEXT_DOMAIN ) ?></option>
				<?php foreach ( range( date( 'Y' ), date( 'Y' ) + 10 ) as $year ) : ?>
					<option value="<?php echo $year ?>"><?php echo $year ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<div class="clear"></div>
	</div>

	<?php if ( $cvv_required ) :

		?><p class="form-row form-row-last" id="braintree-cc-cvv-section"><?php

		// cvv
		woocommerce_form_field( 'braintree-cc-cvv', array(
			'type'              => 'text',
			'label'             => __( 'Card Security Code', WC_Braintree::TEXT_DOMAIN ),
			'maxlength'         => 4,
			'required'          => true,
			'custom_attributes' => array( 'autocomplete' => 'off', 'data-encrypted-name' => 'cvv', 'style' => 'width:60px;' ),
			'class'             => array( 'form-row-first', 'validate-cc-cvv' ),
			'clear'             => true,
		) );

		?></p>

	<?php endif; ?>

</fieldset>
