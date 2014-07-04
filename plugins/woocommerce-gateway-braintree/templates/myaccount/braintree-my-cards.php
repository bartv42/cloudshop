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
 * Template description
 *
 * @param string $name document any passed in "parameters"
 *
 * @version 2.0
 * @since 2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

?> <h2 id="braintree-my-saved-cards" style="margin-top:40px;"><?php _e( 'My Saved Cards', WC_Braintree::TEXT_DOMAIN ); ?></h2><?php

if ( ! empty( $cards ) ) :
	?>
	<a name="braintree-my-saved-cards"></a>
	<table class="shop_table my-account-braintree-saved-cards">

		<thead>
		<tr>
			<th class="braintree-card-type"><span class="nobr"><?php _e( 'Card Type', WC_Braintree::TEXT_DOMAIN ); ?></span></th>
			<th class="braintree-card-last-four"><span class="nobr"><?php _e( 'Last Four', WC_Braintree::TEXT_DOMAIN ); ?></span></th>
			<th class="braintree-card-exp-date"><span class="nobr"><?php _e( 'Expires', WC_Braintree::TEXT_DOMAIN ); ?></span></th>
			<th class="braintree-card-status"><span class="nobr"><?php _e( 'Status', WC_Braintree::TEXT_DOMAIN ); ?></span></th>
			<th class="braintree-card-actions"><span class="nobr"><?php _e( 'Actions', WC_Braintree::TEXT_DOMAIN ); ?></span></th>
		</tr>
		</thead>

		<tbody>
		<?php foreach ( $cards as $card ) :
			$delete_url      = wp_nonce_url( add_query_arg( array( 'braintree-cc-token' => $card->token, 'braintree-action' => 'delete' ) ), 'braintree-security' );
			$set_default_url = wp_nonce_url( add_query_arg( array( 'braintree-cc-token' => $card->token, 'braintree-action' => 'set-default' ) ),'braintree-security' );
			?>
			<tr class=braintree-card">
				<td class="card-type">
					<img src="<?php echo esc_url( $card->imageUrl ); ?>" width="40" alt="<?php echo esc_attr( $card->cardType ); ?>" title="<?php echo esc_attr( $card->cardType ); ?>" />
				</td>
				<td class="card-last-four">
					<?php echo esc_html( $card->last4 ); ?>
				</td>
				<td class="card-exp-date">
					<?php echo esc_html( $card->expirationDate ); ?>
				</td>
				<td class="card-status">
					<?php echo ( $card->isDefault() ) ? __( 'Default', WC_Braintree::TEXT_DOMAIN ) : '<a href="' . esc_url( $set_default_url ) . '">' . __( 'Make Default', WC_Braintree::TEXT_DOMAIN ) . '</a>'; ?>
				</td>
				<td class="card-actions" style="width: 1%; text-align: center;">
					<a href="<?php echo esc_url( $delete_url ); ?>" class="braintree-delete-saved-card"><img src="<?php echo esc_attr( $GLOBALS['wc_braintree']->get_plugin_url() . '/assets/images/cross.png' ); ?>" alt="[X]" /></a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>

	</table>
<?php

else :

	?><p><?php _e( 'You do not have any saved cards.', WC_Braintree::TEXT_DOMAIN ); ?></p><?php

endif;
